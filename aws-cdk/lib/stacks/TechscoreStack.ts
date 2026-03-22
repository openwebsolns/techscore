import { Stack, StackProps } from "aws-cdk-lib";
import { ICertificate } from "aws-cdk-lib/aws-certificatemanager";
import { Construct } from "constructs";
import { loadRootHostedZone } from "./common";
import { PublicSite } from "../constructs/PublicSite";
import { EmailSettings } from "../constructs/EmailSettings";
import { Application } from "../constructs/Application";

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
  readonly scoresCertificate: ICertificate;

  /**
   * Certificate associated with the application, created in us-east-1.
   */
  readonly appCertificate: ICertificate;
}

export class TechscoreStack extends Stack {
  constructor(scope: Construct, id: string, props: TechscoreStackProps) {
    super(scope, id, props);

    const rootHostedZone = loadRootHostedZone(this);

    // Public site
    const publicSite = new PublicSite(this, {
      rootHostedZone,
      certificate: props.scoresCertificate,
    });

    // Email setup
    new EmailSettings(this, {
      rootHostedZone,
      sendingEnabled: props.serviceStatus === TechscoreServiceStatus.PUBLIC,
    });

    // Application site
    new Application(this, {
      rootHostedZone,
      certificate: props.appCertificate,
      scoresBucket: publicSite.scoresBucket,
    });
  }
}
