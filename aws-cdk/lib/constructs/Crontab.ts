import { Duration, TimeZone } from "aws-cdk-lib";
import { ISecurityGroup, SubnetType } from "aws-cdk-lib/aws-ec2";
import { ICluster, TaskDefinition } from "aws-cdk-lib/aws-ecs";
import {
  CronOptionsWithTimezone,
  Schedule,
  ScheduleExpression,
  ScheduleGroup,
  ScheduleTargetInput,
} from "aws-cdk-lib/aws-scheduler";
import { EcsRunFargateTask } from "aws-cdk-lib/aws-scheduler-targets";
import { IQueue, Queue } from "aws-cdk-lib/aws-sqs";
import { Construct } from "constructs";

export interface CrontabProps {
  readonly taskDefinition: TaskDefinition;
  readonly cluster: ICluster;
  readonly securityGroups: ISecurityGroup[];
}

/**
 * Executes all scheduled jobs for Techscore Application.
 */
export class Crontab extends Construct {
  private readonly group: ScheduleGroup;
  private readonly props: CrontabProps;
  private readonly deadLetterQueue: IQueue;

  constructor(scope: Construct, props: CrontabProps) {
    super(scope, "Crontab");

    this.props = props;
    this.group = new ScheduleGroup(this, "Group", {
      scheduleGroupName: "TechscoreScheduledJobs",
    });
    this.deadLetterQueue = new Queue(this, "DLQ");

    // Update the front page every day, just in case
    this.newJob("UpdateFront", ["-f"], {
      minute: "24",
      hour: "3",
    });
  }

  private newJob(
    script: string,
    args: string[],
    cron: CronOptionsWithTimezone,
  ): Schedule {
    return new Schedule(this, script, {
      schedule: ScheduleExpression.cron({
        timeZone: TimeZone.AMERICA_NEW_YORK,
        ...cron,
      }),
      scheduleName: script,
      scheduleGroup: this.group,
      target: new EcsRunFargateTask(this.props.cluster, {
        taskCount: 1,
        taskDefinition: this.props.taskDefinition,
        securityGroups: this.props.securityGroups,
        deadLetterQueue: this.deadLetterQueue,
        vpcSubnets: {
          subnetType: SubnetType.PUBLIC,
        },
        maxEventAge: Duration.hours(1),
        input: ScheduleTargetInput.fromObject({
          containerOverrides: [
            {
              command: ["php", "/var/www/bin/cli.php", script, ...args],
              name: "application",
            },
          ],
        }),
      }),
    });
  }
}
