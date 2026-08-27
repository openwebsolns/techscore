import { ICertificate } from "aws-cdk-lib/aws-certificatemanager";
import {
  AllowedMethods,
  Distribution,
  PriceClass,
  ViewerProtocolPolicy,
} from "aws-cdk-lib/aws-cloudfront";
import { S3BucketOrigin } from "aws-cdk-lib/aws-cloudfront-origins";
import { ARecord, RecordTarget } from "aws-cdk-lib/aws-route53";
import { CloudFrontTarget } from "aws-cdk-lib/aws-route53-targets";
import { Bucket, IBucket } from "aws-cdk-lib/aws-s3";
import { Construct } from "constructs";
import { HostedZoneInfo } from "../stacks/common";

export interface PublicSiteProps {
  readonly rootHostedZone: HostedZoneInfo;
  readonly certificate: ICertificate;
}

export class PublicSite extends Construct {
  public readonly scoresBucket: IBucket;

  constructor(scope: Construct, props: PublicSiteProps) {
    super(scope, "PublicSite");

    this.scoresBucket = new Bucket(this, "Scores", {
      versioned: true,
      websiteIndexDocument: "index.html",
      websiteErrorDocument: "404.html",
      enforceSSL: true,
    });

    const domainName = `scores.${props.rootHostedZone.zoneName}`;

    // CloudFront distribution should be set up with the domain name above.
    // However, if this domain is already assigned to some other distribution,
    // then CloudFront will reject it. Allow customers to skip domain name
    // registration with the distro in such cases.
    const domainNames: string[] = [];
    if (!props.rootHostedZone.skipPublicDistributionDomainName) {
      domainNames.push(domainName);
    }

    const distribution = new Distribution(this, "Distribution", {
      domainNames,
      defaultBehavior: {
        origin: S3BucketOrigin.withOriginAccessControl(this.scoresBucket),
        allowedMethods: AllowedMethods.ALLOW_GET_HEAD_OPTIONS,
        viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
      },
      defaultRootObject: "index.html",
      errorResponses: [
        {
          httpStatus: 403,
          responseHttpStatus: 404,
          responsePagePath: "/404.html",
        },
      ],
      priceClass: PriceClass.PRICE_CLASS_100,
      certificate: props.certificate,
    });

    // Create alias entry for CloudFront distro
    if (props.rootHostedZone.hostedZone) {
      new ARecord(this, "AliasRecord", {
        zone: props.rootHostedZone.hostedZone,
        recordName: domainName,
        target: RecordTarget.fromAlias(new CloudFrontTarget(distribution)),
      });
    }
  }
}
