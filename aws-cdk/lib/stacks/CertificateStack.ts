import { Construct } from "constructs";
import { Stack, StackProps } from "aws-cdk-lib";
import {
  Certificate,
  CertificateValidation,
} from "aws-cdk-lib/aws-certificatemanager";
import { loadRootHostedZone } from "./common";

export interface CertificateStackProps extends StackProps {}

/**
 * Creates public certificates in us-east-1 region.
 *
 * CloudFront distributions require custom certificates in us-east-1 region.
 * This stack creates the certificate in that region so that the TechscoreStack
 * can reference it.
 */
export class CertificateStack extends Stack {
  readonly scoresCertificate: Certificate;
  readonly appCertificate: Certificate;

  constructor(scope: Construct, id: string, props: CertificateStackProps) {
    if (props.env?.region !== "us-east-1") {
      throw new Error("CertificateStack must be launched in us-east-1 region");
    }

    super(scope, id, props);

    const rootHostedZone = loadRootHostedZone(this);

    this.scoresCertificate = new Certificate(this, "ScoresCertificate", {
      domainName: `scores.${rootHostedZone.zoneName}`,
      validation: CertificateValidation.fromDns(rootHostedZone),
    });

    this.appCertificate = new Certificate(this, "TsCertificate", {
      domainName: `ts.${rootHostedZone.zoneName}`,
      validation: CertificateValidation.fromDns(rootHostedZone),
    });
  }
}
