import { Construct } from "constructs";
import { IHostedZone, PublicHostedZone } from "aws-cdk-lib/aws-route53";

const getContextAccount = (scope: Construct) => {
  const contextValue = scope.node.tryGetContext("account");
  if (!(typeof contextValue === "string")) {
    throw new Error(
      `Missing "account" context value; pass via '--context account=$accountId'`,
    );
  }
  return contextValue;
};

const getContextKey = (scope: Construct, key: string): string => {
  const fullKey = `${key}:account=${getContextAccount(scope)}`;
  const contextValue = scope.node.tryGetContext(fullKey);
  if (!(typeof contextValue === "string")) {
    throw new Error(`Unable to find string value for context key '${key}'`);
  }

  return contextValue;
};

export interface HostedZoneInfo {
  readonly zoneName: string;
  readonly hostedZone?: IHostedZone;
}

export const loadRootHostedZone = (scope: Construct): HostedZoneInfo => {
  const zoneName = getContextKey(scope, "root-zone-name");
  const hostedZoneId = getContextKey(scope, "root-hosted-zone-id");

  return {
    zoneName,
    hostedZone:
      hostedZoneId === "MANUALLY_UPDATED"
        ? undefined
        : PublicHostedZone.fromHostedZoneAttributes(scope, "RootHostedZone", {
            zoneName,
            hostedZoneId,
          }),
  };
};
