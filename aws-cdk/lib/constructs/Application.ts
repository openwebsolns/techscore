import { Construct } from "constructs";
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
  CachePolicy,
  Distribution,
  OriginProtocolPolicy,
  OriginRequestPolicy,
  PriceClass,
  ViewerProtocolPolicy,
} from "aws-cdk-lib/aws-cloudfront";
import { S3BucketOrigin, VpcOrigin } from "aws-cdk-lib/aws-cloudfront-origins";
import { ARecord, IHostedZone, RecordTarget } from "aws-cdk-lib/aws-route53";
import { Bucket, IBucket } from "aws-cdk-lib/aws-s3";
import { BucketDeployment, Source } from "aws-cdk-lib/aws-s3-deployment";
import path = require("path");
import { LogGroup, RetentionDays } from "aws-cdk-lib/aws-logs";
import { IQueue } from "aws-cdk-lib/aws-sqs";
import { Database } from "./Database";
import { Secret } from "aws-cdk-lib/aws-secretsmanager";
import {
  ContainerImage,
  FargateTaskDefinition,
  Secret as EcsSecret,
  LogDrivers,
  Cluster,
} from "aws-cdk-lib/aws-ecs";
import { ApplicationLoadBalancedFargateService } from "aws-cdk-lib/aws-ecs-patterns";
import { CloudFrontTarget } from "aws-cdk-lib/aws-route53-targets";
import { Effect, PolicyStatement } from "aws-cdk-lib/aws-iam";
import { Crontab } from "./Crontab";

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

    new BucketDeployment(this, "FaviconDeployment", {
      destinationBucket: assetsBucket,
      sources: [
        Source.asset(path.join(__dirname, "..", "..", "..", "www"), {
          exclude: ["**", "!favicon.ico"], // Exclude everything except the specific file
        }),
      ],
    });

    const database = new Database(this, { vpc });

    const passwordSalt = new Secret(this, "PasswordSalt", {
      description: "Salt used to encrypt user passwords",
    });

    const taskDefinition = new FargateTaskDefinition(this, "TaskDefinition", {
      cpu: 256,
      memoryLimitMiB: 512,
    });

    const logGroup = new LogGroup(this, "Logs", {
      retention: RetentionDays.THREE_MONTHS,
    });

    taskDefinition.addContainer("Techscore", {
      image: ContainerImage.fromAsset(path.join(__dirname, "..", "..", ".."), {
        exclude: [
          "CodeDeploy",
          "aws-cdk",
          "doc",
          "etc",
          "html",
          "res",
          "tst",
          "*.sh",
          "*.md",
          "Makefile",
          "Dockerfile",
        ],
      }),
      essential: true,
      containerName: "application",
      environment: {
        CONF_LOCAL_FILE: "conf.default-aws-ecs.php",
        // See file above for set of environment variables used; AWS_* provided by Lambda
        APP_HOME: `ts.${props.rootHostedZone.zoneName}`,
        PUB_HOME: `scores.${props.rootHostedZone.zoneName}`,
        ADMIN_MAIL: "admin@openweb-solutions.net",
        SCORES_BUCKET: props.scoresBucket.bucketName,
        SQS_BOUNCE_QUEUE_URL: props.emailBounceQueue.queueUrl,
        SQL_PORT: String(database.endpointPort),
        SQL_HOST: database.endpointAddress,
        SQL_USER: "admin",
        SQL_DB: "techscore",
      },
      secrets: {
        PASSWORD_SALT: EcsSecret.fromSecretsManager(passwordSalt),
        ADMIN_PASS: EcsSecret.fromSecretsManager(
          new Secret(this, "AdminPass", {
            description: "Password for ADMIN_MAIL account",
          }),
        ),
        SQL_PASS: EcsSecret.fromSecretsManager(
          database.adminPasswordSecret,
          "password",
        ),
      },
      // healthCheck: {},
      logging: LogDrivers.awsLogs({
        logGroup,
        streamPrefix: "ts-logs",
      }),
      portMappings: [{ containerPort: 80 }],
    });

    const cluster = new Cluster(this, "Cluster", {
      clusterName: "TechscoreApp",
      vpc,
    });

    const service = new ApplicationLoadBalancedFargateService(this, "Service", {
      cluster,
      taskDefinition,
      publicLoadBalancer: false,
      minHealthyPercent: 100,
      assignPublicIp: true, // needed for tasks to pull images from ECR since they're in public subnet
      taskSubnets: {
        subnetType: SubnetType.PUBLIC,
      },
      enableExecuteCommand: true,
    });

    // Expect a 403 when hitting / as part of health checks
    service.targetGroup.configureHealthCheck({
      healthyHttpCodes: "403",
    });

    props.scoresBucket.grantReadWrite(taskDefinition.taskRole);
    props.emailBounceQueue.grantConsumeMessages(taskDefinition.taskRole);
    database.connections.allowFrom(
      service.service,
      Port.tcp(database.endpointPort),
    );
    database.adminPasswordSecret.grantRead(taskDefinition.taskRole);
    passwordSalt.grantRead(taskDefinition.taskRole);
    logGroup.grantWrite(taskDefinition.taskRole);
    taskDefinition.addToTaskRolePolicy(
      new PolicyStatement({
        actions: ["cloudwatch:PutMetricData"],
        effect: Effect.ALLOW,
        resources: ["*"],
      }),
    );

    new Crontab(this, {
      taskDefinition,
      cluster,
      securityGroups: service.service.connections.securityGroups,
    });

    const assetsOrigin = {
      origin: S3BucketOrigin.withOriginAccessControl(assetsBucket),
      allowedMethods: AllowedMethods.ALLOW_GET_HEAD_OPTIONS,
      viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
    };

    const domainName = `ts.${props.rootHostedZone.zoneName}`;
    const distribution = new Distribution(this, "Distribution", {
      defaultBehavior: {
        origin: VpcOrigin.withApplicationLoadBalancer(service.loadBalancer, {
          protocolPolicy: OriginProtocolPolicy.HTTP_ONLY,
        }),
        allowedMethods: AllowedMethods.ALLOW_ALL,
        cachePolicy: CachePolicy.USE_ORIGIN_CACHE_CONTROL_HEADERS_QUERY_STRINGS,
        viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
        originRequestPolicy: OriginRequestPolicy.ALL_VIEWER_EXCEPT_HOST_HEADER,
      },
      additionalBehaviors: {
        "/inc/*": assetsOrigin,
        "/favicon.*": assetsOrigin,
      },
      priceClass: PriceClass.PRICE_CLASS_100,
      domainNames: [domainName],
      certificate: props.certificate,
    });

    // Create alias entry for CloudFront distro
    new ARecord(this, "AliasRecord", {
      zone: props.rootHostedZone,
      recordName: domainName,
      target: RecordTarget.fromAlias(new CloudFrontTarget(distribution)),
    });
  }
}
