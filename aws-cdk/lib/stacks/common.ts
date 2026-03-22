import { Construct } from "constructs";
import { PublicHostedZone } from "aws-cdk-lib/aws-route53";

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

export const loadRootHostedZone = (scope: Construct) =>
  PublicHostedZone.fromHostedZoneAttributes(scope, "RootHostedZone", {
    zoneName: getContextKey(scope, "root-zone-name"),
    hostedZoneId: getContextKey(scope, "root-hosted-zone-id"),
  });
