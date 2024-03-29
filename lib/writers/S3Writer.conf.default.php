<?php
/**
 * Configuration parameters for S3Writer.
 *
 * Copy this file to S3Writer.conf.local.php and update
 *
 * @deprecated 2024-02-12: use params instead
 *
 * @author Dayan Paez
 * @created 2012-10-09
 * @package tscore/writers
 */

$this->bucket = '';
$this->aws_creds_provider = null; // aws\auth\AwsCredsProvider
$this->host_base = '';
