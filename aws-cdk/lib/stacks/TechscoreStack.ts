import { Duration, Stack, StackProps } from 'aws-cdk-lib';
import { ICertificate } from 'aws-cdk-lib/aws-certificatemanager';
import { AllowedMethods, Distribution, PriceClass, ViewerProtocolPolicy } from 'aws-cdk-lib/aws-cloudfront';
import { S3BucketOrigin } from 'aws-cdk-lib/aws-cloudfront-origins';
import { InstanceClass, InstanceSize, InstanceType, Vpc } from 'aws-cdk-lib/aws-ec2';
import { RetentionDays } from 'aws-cdk-lib/aws-logs';
import { DatabaseInstance, DatabaseInstanceEngine, MariaDbEngineVersion } from 'aws-cdk-lib/aws-rds';
import { Bucket } from 'aws-cdk-lib/aws-s3';
import { ConfigurationSet, ConfigurationSetTlsPolicy, EmailIdentity, EmailSendingEvent, EventDestination, Identity } from 'aws-cdk-lib/aws-ses';
import { Topic } from 'aws-cdk-lib/aws-sns';
import { SqsSubscription } from 'aws-cdk-lib/aws-sns-subscriptions';
import { Queue } from 'aws-cdk-lib/aws-sqs';
import { Construct } from 'constructs';
import { loadRootHostedZone } from './common';

export enum TechscoreServiceStatus {
  BUILDING,
  PUBLIC,
}

export interface TechscoreStackProps extends StackProps {
  /**
   * Status for service.
   *
   * - BUILDING: service is not public; e-mails are not sent;
   * - PUBLIC: service is customer facing; e-mails are sent;
   */
  readonly serviceStatus: TechscoreServiceStatus;

  /**
   * Certificate to use for scores distribution, created in us-east-1.
   */
  readonly scoresCertificate: ICertificate
}

export class TechscoreStack extends Stack {
  constructor(scope: Construct, id: string, props: TechscoreStackProps) {
    super(scope, id, props);

    const rootHostedZone = loadRootHostedZone(this);

    // Public site
    const scoresBucket = new Bucket(this, 'Scores', {
      versioned: true,
      websiteIndexDocument: 'index.html',
      websiteErrorDocument: '404.html',
      enforceSSL: true,
    });

    const scoresDomainName = `scores.${rootHostedZone.zoneName}`;

    new Distribution(this, 'ScoresDistribution', {
      defaultBehavior: {
        origin: S3BucketOrigin.withOriginAccessControl(scoresBucket),
        allowedMethods: AllowedMethods.ALLOW_GET_HEAD_OPTIONS,
        viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
      },
      defaultRootObject: 'index.html',
      priceClass: PriceClass.PRICE_CLASS_100,
      domainNames: [scoresDomainName],
      certificate: props.scoresCertificate,
    });

    // Email setup
    // Set up a queue to handle e-mail bounces and protect reputation per SqsBounceHandler
    const emailBounceQueue = new Queue(this, 'EmailNotificationsQueue');
    const emailBounceTopic = new Topic(this, 'EmailNotificationsTopic');
    emailBounceTopic.addSubscription(new SqsSubscription(emailBounceQueue, {
      rawMessageDelivery: true,
    }));

    const configurationSet = new ConfigurationSet(this, 'ConfigurationSet', {
      reputationMetrics: true,
      sendingEnabled: props.serviceStatus === TechscoreServiceStatus.PUBLIC,
      tlsPolicy: ConfigurationSetTlsPolicy.REQUIRE,
    });
    configurationSet.addEventDestination('ToSns', {
      destination: EventDestination.snsTopic(emailBounceTopic),
      events: [EmailSendingEvent.COMPLAINT, EmailSendingEvent.REJECT],
    });

    const emailIdentity = new EmailIdentity(this, 'EmailIdentity', {
      identity: Identity.publicHostedZone(rootHostedZone),
      mailFromDomain: `mail.${rootHostedZone.zoneName}`,
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
