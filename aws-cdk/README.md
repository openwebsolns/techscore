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
