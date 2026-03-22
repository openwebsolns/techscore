#!/usr/bin/env node
import "source-map-support/register";
import * as cdk from "aws-cdk-lib";
import {
  CertificateStack,
  TechscoreServiceStatus,
  TechscoreStack,
} from "../lib/stacks";

const app = new cdk.App();

const certificateStack = new CertificateStack(app, "Certificates", {
  crossRegionReferences: true,
  env: {
    region: "us-east-1",
  },
});

new TechscoreStack(app, "Techscore", {
  crossRegionReferences: true,
  env: {
    region: "us-east-2",
  },
  serviceStatus: TechscoreServiceStatus.BUILDING,
  appCertificate: certificateStack.appCertificate,
  scoresCertificate: certificateStack.scoresCertificate,
});
