import { ICertificate } from "aws-cdk-lib/aws-certificatemanager";
import {
  AllowedMethods,
  Distribution,
  PriceClass,
  ViewerProtocolPolicy,
} from "aws-cdk-lib/aws-cloudfront";
import { S3BucketOrigin } from "aws-cdk-lib/aws-cloudfront-origins";
import { IHostedZone } from "aws-cdk-lib/aws-route53";
import { Bucket, IBucket } from "aws-cdk-lib/aws-s3";
import { Construct } from "constructs";

export interface PublicSiteProps {
  readonly rootHostedZone: IHostedZone;
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

    new Distribution(this, "Distribution", {
      defaultBehavior: {
        origin: S3BucketOrigin.withOriginAccessControl(this.scoresBucket),
        allowedMethods: AllowedMethods.ALLOW_GET_HEAD_OPTIONS,
        viewerProtocolPolicy: ViewerProtocolPolicy.REDIRECT_TO_HTTPS,
      },
      defaultRootObject: "index.html",
      priceClass: PriceClass.PRICE_CLASS_100,
      domainNames: [`scores.${props.rootHostedZone.zoneName}`],
      certificate: props.certificate,
    });
  }
}
