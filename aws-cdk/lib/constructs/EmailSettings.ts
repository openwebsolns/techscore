import {
  ConfigurationSet,
  ConfigurationSetTlsPolicy,
  EmailIdentity,
  EmailSendingEvent,
  EventDestination,
  Identity,
} from "aws-cdk-lib/aws-ses";
import { Topic } from "aws-cdk-lib/aws-sns";
import { SqsSubscription } from "aws-cdk-lib/aws-sns-subscriptions";
import { IQueue, Queue } from "aws-cdk-lib/aws-sqs";
import { Construct } from "constructs";
import { IHostedZone } from "aws-cdk-lib/aws-route53";

export enum TechscoreServiceStatus {
  BUILDING,
  PUBLIC,
}

export interface EmailSettingsProps {
  readonly rootHostedZone: IHostedZone;
  /**
   * Should e-mail be enabled.
   */
  readonly sendingEnabled: boolean;
}

export class EmailSettings extends Construct {
  public readonly emailBounceQueue: IQueue;

  constructor(scope: Construct, props: EmailSettingsProps) {
    super(scope, "EmailSettings");

    // Set up a queue to handle e-mail bounces and protect reputation per SqsBounceHandler
    this.emailBounceQueue = new Queue(this, "EmailNotificationsQueue");
    const emailBounceTopic = new Topic(this, "EmailNotificationsTopic");
    emailBounceTopic.addSubscription(
      new SqsSubscription(this.emailBounceQueue, {
        rawMessageDelivery: true,
      }),
    );

    const configurationSet = new ConfigurationSet(this, "ConfigurationSet", {
      reputationMetrics: true,
      sendingEnabled: props.sendingEnabled,
      tlsPolicy: ConfigurationSetTlsPolicy.REQUIRE,
    });
    configurationSet.addEventDestination("ToSns", {
      destination: EventDestination.snsTopic(emailBounceTopic),
      events: [EmailSendingEvent.COMPLAINT, EmailSendingEvent.REJECT],
    });

    new EmailIdentity(this, "EmailIdentity", {
      identity: Identity.publicHostedZone(props.rootHostedZone),
      mailFromDomain: `mail.${props.rootHostedZone.zoneName}`,
    });
  }
}
