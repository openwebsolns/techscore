# Welcome to your CDK TypeScript project

This is a blank project for CDK development with TypeScript.

The `cdk.json` file tells the CDK Toolkit how to execute your app.

## Useful commands

- `npm run build` compile typescript to js
- `npm run watch` watch for changes and compile
- `npm run test` perform the jest unit tests
- `npx cdk deploy` deploy this stack to your default AWS account/region
- `npx cdk diff` compare deployed stack with current state
- `npx cdk synth` emits the synthesized CloudFormation template

## Recipes

```bash
# --profile for selecting account, --context key to fetch context values
npm run cdk synth -- --profile website --context account=818080407466

# Deploy
npm run cdk deploy -- --profile website --context account=818080407466 --all
```

## Configuration

The following CDK flags must be configured, i.e. in `~/.cdk.json`:

```json
{
  "context": {
    "skip-public-distribution-domain-name:account=000011112222": "false",
    "root-zone-name:account=000011112222": "subdomain.openweb-solutions.net",
    "root-hosted-zone-id:account=000011112222": "Z061792429..."
  }
}
```

Where the flags have the following meaning:

- `root-zone-name`: the root DNS name for Techscore. The application will be created under subdomain
  `ts.${root-zone-name}` and the public site under `scores.${root-zone-name}`.

- `root-hosted-zone-id`: the Route53 HostedZone ID that owns the `root-zone-name`. This hosted zone
  ID must be created separately, and registered with a registrar. If not using Route53 for DNS
  management, then the string `"MANUALLY_UPDATED"` must be used instead. In that case, creating
  certificates will pause until appropriate DNS entries are manually entered in the DNS records. In
  addition, aliases and CNAMEs that CDK would otherwise provide via Route53 integration must also be
  added manually.

- `skip-public-distribution-domain-name`: if set to "true", then the custom domain name for
  the public site will _not_ be attached to the CloudFront distribution. This is useful when that
  domain name is already in use by another CloudFront distribution (e.g. while migrating an
  installation of Techscore to a different account).
