import { Construct } from "constructs";
import { Duration, Stack } from "aws-cdk-lib";
import { ICertificate } from "aws-cdk-lib/aws-certificatemanager";
import {
  InterfaceVpcEndpoint,
  InterfaceVpcEndpointAwsService,
  Port,
  SubnetType,
  Vpc,
} from "aws-cdk-lib/aws-ec2";
import {
  AllowedMethods,
  Distribution,
  PriceClass,
  ViewerProtocolPolicy,
} from "aws-cdk-lib/aws-cloudfront";
import { S3BucketOrigin } from "aws-cdk-lib/aws-cloudfront-origins";
import { IHostedZone } from "aws-cdk-lib/aws-route53";
import { Bucket, IBucket } from "aws-cdk-lib/aws-s3";
import { BucketDeployment, Source } from "aws-cdk-lib/aws-s3-deployment";
import path = require("path");
import {
  AssetCode,
  Function,
  LayerVersion,
  LoggingFormat,
  ParamsAndSecretsLayerVersion,
  ParamsAndSecretsVersions,
  Runtime,
} from "aws-cdk-lib/aws-lambda";
import { LogGroup, RetentionDays } from "aws-cdk-lib/aws-logs";
import { IQueue } from "aws-cdk-lib/aws-sqs";
import { Database } from "./Database";
import { Secret } from "aws-cdk-lib/aws-secretsmanager";

export interface ApplicationProps {
  readonly rootHostedZone: IHostedZone;
  readonly scoresBucket: IBucket;
  readonly certificate: ICertificate;
  readonly emailBounceQueue: IQueue;
}

/**
 * Main construct for the scoring (application) side.
 */
export class Application extends Construct {
  constructor(scope: Construct, props: ApplicationProps) {
    super(scope, "Application");

    // To avoid NAT Gateways, place Lambda functions in public subnet
    const vpc = new Vpc(this, "Vpc", {
      maxAzs: 2,
      natGateways: 0,
      subnetConfiguration: [
        {
          cidrMask: 24,
          name: "app",
          subnetType: SubnetType.PUBLIC,
        },
        {
          cidrMask: 24,
          name: "db",
          subnetType: SubnetType.PRIVATE_ISOLATED,
        },
      ],
    });

    new InterfaceVpcEndpoint(this, "SecretsEndpoint", {
      service: InterfaceVpcEndpointAwsService.SECRETS_MANAGER,
      vpc,
    });

    // Create a distribution with multiple origins: one for static assets
    // that goes to an S3 bucket, and another that goes to Lambda
    const assetsBucket = new Bucket(this, "Assets", {
      versioned: true,
      enforceSSL: true,
    });

    new BucketDeployment(this, "AssetsDeployment", {
      destinationBucket: assetsBucket,
      destinationKeyPrefix: "inc/",
      sources: [
        Source.asset(path.join(__dirname, "..", "..", "..", "www", "inc")),
      ],
    });

    const database = new Database(this, { vpc });

    const passwordSalt = new Secret(this, "PasswordSalt", {
      description: "Salt used to encrypt user passwords",
    });

    const stack = Stack.of(this);
    const app = new Function(this, "App", {
      description: "Main Techscore application",
      code: new AssetCode(path.join(__dirname, "..", "..", ".."), {
        exclude: [
          ".??*",
          "CodeDeploy",
          "aws-cdk",
          "bin",
          "doc",
          "etc",
          "html",
          "res",
          "tst",
          "www",
          "*.sh",
          "*.md",
          "Makefile",
          "Dockerfile",
        ],
      }),
      runtime: Runtime.PROVIDED_AL2023,
      handler: "lib/lambda-main.handler",
      layers: [
        // Layer must be deployed first, see https://github.com/coldfusionjp/aws-lambda-php-runtime
        LayerVersion.fromLayerVersionArn(
          this,
          "PhpLayer",
          `arn:${stack.partition}:lambda:${stack.region}:${stack.account}:layer:php-7_4_33-x86_64-runtime:1`,
        ),
      ],
      paramsAndSecrets: ParamsAndSecretsLayerVersion.fromVersion(
        ParamsAndSecretsVersions.V1_0_103,
      ),
      environment: {
        CONF_LOCAL_FILE: "conf.aws-lambda.php",
        // See file above for set of environment variables used; AWS_* provided by Lambda
        APP_HOME: `ts.${props.rootHostedZone.zoneName}`,
        PUB_HOME: `scores.${props.rootHostedZone.zoneName}`,
        ADMIN_MAIL: "admin@openweb-solutions.net",
        SCORES_BUCKET: props.scoresBucket.bucketName,
        PASSWORD_SALT: `aws-secret:${passwordSalt.secretName}`,
        SQS_BOUNCE_QUEUE_URL: props.emailBounceQueue.queueUrl,
        SQL_PORT: String(database.endpointPort),
        SQL_HOST: database.endpointAddress,
        SQL_USER: "admin",
        SQL_PASS: `aws-secret:${database.adminPasswordSecret.secretName}`,
        SQL_DB: "techscore",
      },
      timeout: Duration.seconds(30),
      logGroup: new LogGroup(this, "Logs", {
        retention: RetentionDays.THREE_MONTHS,
      }),
      vpc,
    });

    props.scoresBucket.grantReadWrite(app);
    props.emailBounceQueue.grantConsumeMessages(app);
    database.connections.allowFrom(app, Port.tcp(database.endpointPort));
    database.adminPasswordSecret.grantRead(app);
    passwordSalt.grantRead(app);

    // new Distribution(this, "Distribution", {
    //   defaultBehavior: {
    //     origin: S3BucketOrigin.withOriginAccessControl(this.scoresBucket),
    //     allowedMethods: AllowedMethods.ALLOW_GET_HEAD_OPTIONS,
    //     viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
    //   },
    //   defaultRootObject: "index.html",
    //   priceClass: PriceClass.PRICE_CLASS_100,
    //   domainNames: [`ts.${props.rootHostedZone.zoneName}`],
    //   certificate: props.certificate,
    // });
  }
}
