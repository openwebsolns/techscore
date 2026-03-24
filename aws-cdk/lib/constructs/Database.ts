import { Duration } from "aws-cdk-lib";
import {
  Connections,
  IConnectable,
  InstanceClass,
  InstanceSize,
  InstanceType,
  IVpc,
  SubnetType,
} from "aws-cdk-lib/aws-ec2";
import { RetentionDays } from "aws-cdk-lib/aws-logs";
import {
  DatabaseInstance,
  DatabaseInstanceEngine,
  MariaDbEngineVersion,
} from "aws-cdk-lib/aws-rds";
import { ISecret } from "aws-cdk-lib/aws-secretsmanager";
import { Construct } from "constructs";

export interface DatabaseProps {
  readonly vpc: IVpc;
}

export class Database extends Construct implements IConnectable {
  private readonly database: DatabaseInstance;

  constructor(scope: Construct, props: DatabaseProps) {
    super(scope, "Database");

    this.database = new DatabaseInstance(this, "Database", {
      engine: DatabaseInstanceEngine.mariaDb({
        version: MariaDbEngineVersion.VER_11_4_7,
      }),
      allocatedStorage: 50,
      backupRetention: Duration.days(7),
      cloudwatchLogsRetention: RetentionDays.THREE_MONTHS,
      databaseName: "techscore",
      instanceType: InstanceType.of(InstanceClass.T4G, InstanceSize.SMALL),
      multiAz: false, // saves 40%
      parameters: {
        explicit_defaults_for_timestamp: "0",
      },
      preferredMaintenanceWindow: "Tue:22:15-Tue:22:45",
      publiclyAccessible: false,
      vpcSubnets: {
        subnetType: SubnetType.PRIVATE_ISOLATED,
      },
      vpc: props.vpc,
    });
  }

  public get endpointAddress(): string {
    return this.database.instanceEndpoint.hostname;
  }

  public get endpointPort(): number {
    return this.database.instanceEndpoint.port;
  }

  public get adminPasswordSecret(): ISecret {
    return this.database.secret!;
  }

  public get connections(): Connections {
    return this.database.connections;
  }
}
