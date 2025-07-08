import { Duration, Stack, StackProps } from 'aws-cdk-lib';
import { AllowedMethods, Distribution, PriceClass, ViewerProtocolPolicy } from 'aws-cdk-lib/aws-cloudfront';
import { S3BucketOrigin } from 'aws-cdk-lib/aws-cloudfront-origins';
import { InstanceClass, InstanceSize, InstanceType, Vpc } from 'aws-cdk-lib/aws-ec2';
import { RetentionDays } from 'aws-cdk-lib/aws-logs';
import { DatabaseInstance, DatabaseInstanceEngine, MariaDbEngineVersion } from 'aws-cdk-lib/aws-rds';
import { Bucket } from 'aws-cdk-lib/aws-s3';
import { Construct } from 'constructs';

export class TechscoreStack extends Stack {
  constructor(scope: Construct, id: string, props?: StackProps) {
    super(scope, id, props);

    // Public site
    const scoresBucket = new Bucket(this, 'Scores', {
      versioned: true,
      websiteIndexDocument: 'index.html',
      websiteErrorDocument: '404.html',
      enforceSSL: true,
    });

    new Distribution(this, 'ScoresDistribution', {
      defaultBehavior: {
        origin: S3BucketOrigin.withOriginAccessControl(scoresBucket),
        allowedMethods: AllowedMethods.ALLOW_GET_HEAD_OPTIONS,
        viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
      },
      defaultRootObject: 'index.html',
      priceClass: PriceClass.PRICE_CLASS_100,
    });

    // Application site
    const vpc = new Vpc(this, 'Vpc', {
      maxAzs: 2,
    });

    const database = new DatabaseInstance(this, 'Database', {
      engine: DatabaseInstanceEngine.mariaDb({
        version: MariaDbEngineVersion.VER_11_4_7,
      }),
      allocatedStorage: 50,
      backupRetention: Duration.days(7),
      cloudwatchLogsRetention: RetentionDays.THREE_MONTHS,
      databaseName: 'techscore',
      instanceType: InstanceType.of(InstanceClass.T4G, InstanceSize.SMALL),
      multiAz: false, // saves 40%
      parameters: {
        explicit_defaults_for_timestamp: '0',
      },
      preferredMaintenanceWindow: 'Tue:22:15-Tue:22:45',
      publiclyAccessible: false,
      vpc,
    });

    // The code that defines your stack goes here

    // example resource
    // const queue = new sqs.Queue(this, 'AwsCdkQueue', {
    //   visibilityTimeout: cdk.Duration.seconds(300)
    // });
  }
}
