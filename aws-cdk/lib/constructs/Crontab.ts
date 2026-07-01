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

    // Update the 404 pages, once a month works
    this.newJob("Update404", ["-f", "general"], {
      minute: "26",
      hour: "4",
      day: "13",
    });

    // Remove private regattas once a month
    this.newJob("RemovePrivate", ["-vvv"], {
      minute: "26",
      hour: "5",
      day: "28",
    });

    // Remove stale burgees twice a month
    this.newJob("UpdateBurgee", ["-cvvv"], {
      minute: "17",
      hour: "2",
      day: "13,27",
    });

    // Process mail messages
    this.newJob("ProcessOutbox", ["-vvv"], {
      minute: "37",
    });

    // Process mail bounces
    this.newJob("ProcessBouncedEmails", ["-vvv"], {
      minute: "55",
      hour: "8",
    });

    // Update Twitter settings
    this.newJob("TwitterCheck", ["-vvv"], {
      minute: "39",
      hour: "1",
    });

    // Send summary tweets on Thursdays, at 6PM
    this.newJob("TweetSummary", ["-vvv", "coming_soon"], {
      minute: "0",
      hour: "18",
      weekDay: "4",
    });

    // Remove stale sessions from database
    this.newJob("CleanupSessions", ["-vvv"], {
      minute: "43",
    });
    this.newJob("CleanupWebsessionLogs", ["-vvv"], {
      minute: "47",
    });
    this.newJob("CleanupMetrics", ["-vvv"], {
      minute: "49",
      hour: "2",
      day: "4",
    });

    // Remove completed update queues from database
    this.newJob("CleanupCompletedUpdates", ["-vvv"], {
      minute: "31",
      hour: "3",
      day: "3",
    });

    // Email users about pending regattas
    this.newJob("RemindPending", ["-vvv"], {
      minute: "0",
      hour: "18",
      weekDay: "2",
    });
    this.newJob("RemindMissingRP", ["-vvv"], {
      minute: "0",
      hour: "22",
    });
    this.newJob("RemindUpcoming", ["-vvv"], {
      minute: "0",
      hour: "18",
      weekDay: "4",
    });
    this.newJob("AutoFinalize", ["-vvv"], {
      minute: "0",
      hour: "22",
    });

    // Update sailor information: do this every day around 4AM
    this.newJob("SyncDB", ["--log", "-vvv", "schools", "sailors"], {
      minute: "18",
      hour: "4",
    });
    this.newJob("MergeUnregisteredSailors", ["-vvv"], {
      minute: "27",
      hour: "4",
    });
    this.newJob("RolloverEligibleSailors", ["auto", "-vvv"], {
      minute: "33",
      hour: "5",
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
