import { Construct } from 'constructs';
import { PublicHostedZone } from 'aws-cdk-lib/aws-route53';

const getContextKey = (scope: Construct, key: string): string => {
  const contextValue = scope.node.tryGetContext(key);
  if (!(typeof contextValue === 'string')) {
    throw new Error(`Unable to find string value for context key '${key}'`);
  }

  return contextValue;
}

export const loadRootHostedZone = (scope: Construct) => PublicHostedZone.fromHostedZoneAttributes(scope, 'RootHostedZone', {
  zoneName: getContextKey(scope, 'root-zone-name'),
  hostedZoneId: getContextKey(scope, 'root-hosted-zone-id'),
});
