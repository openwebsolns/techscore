import { Construct } from "constructs";
import { ICertificate } from "aws-cdk-lib/aws-certificatemanager";
import { SubnetType, Vpc } from "aws-cdk-lib/aws-ec2";
import {
  AllowedMethods,
  Distribution,
  PriceClass,
  ViewerProtocolPolicy,
} from "aws-cdk-lib/aws-cloudfront";
import { S3BucketOrigin } from "aws-cdk-lib/aws-cloudfront-origins";
import { IHostedZone } from "aws-cdk-lib/aws-route53";
import { Bucket, IBucket } from "aws-cdk-lib/aws-s3";

export interface ApplicationProps {
  readonly rootHostedZone: IHostedZone;
  readonly scoresBucket: IBucket;
  readonly certificate: ICertificate;
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

    // TODO: enable when we're farther along in the development process
    // const database = new Database(this, { vpc });

    // Create a distribution with multiple origins: one for static assets
    // that goes to an S3 bucket, and another that goes to Lambda
    const assetsBucket = new Bucket(this, "Assets", {
      versioned: true,
      enforceSSL: true,
    });

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
