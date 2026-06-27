<?php
namespace writers;

use \aws\AwsRequest;
use \aws\auth\Aws4Signer;
use \aws\auth\AwsCreds;
use \aws\auth\AwsCredsProvider;

use \Writeable;

use \XDoc;
use \XElem;
use \XText;

/**
 * Writes directly to an S3 bucket
 *
 * @author Dayan Paez
 * @created 2012-10-09
 */
class S3Writer extends AbstractWriter {
  const S3_SERVICE_NAME = 's3';
  const EMPTY_BODY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

  const PARAM_AWS_CREDS_PROVIDER = 'aws_creds_provider';
  const PARAM_REGION = 'aws_region';
  const PARAM_BUCKET = 'bucket';
  /**
   * @deprecated This is automatically determined by AWS Region
   * @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/VirtualHosting.html
   */
  const PARAM_HOST_BASE = 'host_base';
  const PARAM_PORT = 'port';
  /**
   * Strips index.html from S3 keys when writing files. Use this setting
   * when using a non-static website S3 bucket to avoid forcing users to
   * include the 'index.html' in the URLs.
   */
  const PARAM_STRIP_INDEX_HTML = 'strip_index_html';

  private $bucket;
  private $aws_creds_provider;
  private $host_base;
  private $port;
  private $awsRegion;
  private $stripIndexHtml;

  /**
   * Creates a new writer with provided params.
   *
   * For backwards compatibility, will check S3Writer.conf.local.php
   * if no parameters provided.
   */
  public function __construct(Array $params) {
    $this->bucket = $params[self::PARAM_BUCKET] ?? null;
    $this->aws_creds_provider = $params[self::PARAM_AWS_CREDS_PROVIDER] ?? null;
    $this->port = $params[self::PARAM_PORT] ?? null;
    $this->awsRegion = $params[self::PARAM_REGION] ?? 'us-west-2';
    $this->host_base = "s3.{$this->awsRegion}.amazonaws.com";
    $this->stripIndexHtml = $params[self::PARAM_STRIP_INDEX_HTML] ?? false;

    if (empty($this->bucket) ||
        empty($this->aws_creds_provider) ||
        empty($this->host_base)) {
      throw new TSWriterException("Missing parameters for S3Writer.");
    }
  }

  /**
   * Helper method: prepare the S3 request
   *
   */
  private function prepRequest($fname) {
    $uri = sprintf('https://%s.%s%s', $this->bucket, $this->host_base, $fname);
    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TS3 Bot');
    if ($this->port !== null)
      curl_setopt($ch, CURLOPT_PORT, $this->port);
    return $ch;                                               
  }

  /**
   * Rudimentary method to determine the type of file based on filename
   *
   * @param String $fname the name of the file
   * @throws TSWriterException if unknown
   */
  private function getMIME($fname) {
    $tokens = explode('.', $fname);
    if (count($tokens) < 2)
      throw new TSWriterException("No extension for file $fname.");
    $suff = array_pop($tokens);
    switch ($suff) {
    case 'html':
      return 'text/html; charset=utf-8';
    case 'png':
      return 'image/png';
    case 'css':
      return 'text/css';
    case 'js':
      return 'text/javascript';
    case 'ico':
      return 'image/x-icon';
    case 'svg':
      return 'image/svg+xml';
    case 'pdf':
      return 'application/pdf';
    case 'gif':
      return 'image/gif';
    case 'jpg':
      return 'image/jpg';
    default:
      throw new TSWriterException("Unknown extension: $suff");
    }
  }

  private function getResourceFilename($resource) {
    $data = stream_get_meta_data($resource);
    return $data['uri'];
  }

  protected function getWrittenResource(Writeable $elem) {
    $fp = tmpfile();
    $elem->write($fp);
    return $fp;
  }

  public function write($fname, Writeable $elem) {
    $fp = $this->getWrittenResource($elem);
    $filename = $this->getResourceFilename($fp);

    $type = $this->getMIME($fname);
    $size = filesize($filename);
    $md5 = base64_encode(md5_file($filename, true));
    $contentHash = hash_file('sha256', $filename); // hexits

    $reqHeaders = array(
      'Host' => $this->bucket . '.' . $this->host_base,
      'Content-Type' => $type,
      'Content-MD5' => $md5,
      'X-Amz-Content-sha256' => $contentHash,
    );

    $s3Key = $this->toS3Key($fname);

    $request = (new AwsRequest(self::S3_SERVICE_NAME, $this->awsRegion))
      ->withMethod(AwsRequest::METHOD_PUT)
      ->withUri($s3Key)
      ->withHeaders($reqHeaders)
      ->withPayloadHash($contentHash);
    $this->signRequest($request);

    $retryableErrors = array(
      28, // CURLE_OPERATION_TIMEDOUT,
    );
    $attempts = 0;
    while (true) {
      fseek($fp, 0);
      $attempts++;

      $ch = $this->prepRequest($s3Key);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $request->curlHeaders());
      curl_setopt($ch, CURLOPT_PUT, true);
      curl_setopt($ch, CURLOPT_INFILE, $fp);
      curl_setopt($ch, CURLOPT_INFILESIZE, $size);

      if (($output = curl_exec($ch)) !== false) {
        break;
      }

      $num = curl_errno($ch);
      if (!in_array($num, $retryableErrors) || $attempts >= 5) {
        $mes = curl_error($ch);
        fclose($fp);
        curl_close($ch);
        throw new TSWriterException(sprintf('%d: %s', $num, $mes));
      }

      // retry
      curl_close($ch);
      sleep(2);
    }

    $data = curl_getinfo($ch);
    fclose($fp);
    curl_close($ch);
    if ($data['http_code'] >= 400)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
  }

  /**
   * Removes the given file or directory.
   *
   * @param $fname file or directory to remove
   */
  public function remove($fname) {
    if (strlen($fname) === 0) {
      throw new TSWriterException("Cannot delete an empty file");
    }

    // If given file ends with a /, treat it as a directory; otherwise as a single file
    if (substr($fname, -1) === '/') {
      $this->removeObjects($this->listobjects(substr($fname, 1), true));
    } else {
      $s3Key = $this->toS3Key($fname);
      $this->removeObjects([$s3Key]);
    }
  }

  private function removeObjects($objs) {
    $cnt = count($objs);
    if ($cnt == 0)
      return;

    require_once('xml5/XmlLib.php');
    for ($round = 0; $round < (int)($cnt / 1000) + 1; $round++) {
      // create XML doc
      $P = new XDoc('Delete', array(), array(new XElem('Quiet', array(), array(new XText("true")))));
      for ($i = $round * 1000; $i < ($round + 1) * 1000 && $i < $cnt; $i++) {
        $P->add(new XElem('Object', array(),
                          array(new XElem('Key', array(), array(new XText(substr($objs[$i], 1)))))));
      }

      // Submit form
      $mes = $P->toXML();
      $md5 = base64_encode(md5($mes, true));
      $reqHeaders = array(
        'Host' => $this->bucket . '.' . $this->host_base,
        'Content-MD5' => $md5,
        'Content-Length' => strval(strlen($mes)),
        'Content-Type' => 'application/xml',
        'X-Amz-Content-sha256' => hash('sha256', $mes),
      );
      $params = array('delete' => '1');

      $request = (new AwsRequest(self::S3_SERVICE_NAME, $this->awsRegion))
        ->withMethod(AwsRequest::METHOD_POST)
        ->withUri('/')
        ->withQueryParams($params)
        ->withHeaders($reqHeaders)
        ->withPayload($mes);
      $this->signRequest($request);

      $ch = $this->prepRequest('/?delete=1');
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $mes);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $request->curlHeaders());

      if (($output = curl_exec($ch)) === false) {
        $mes = curl_error($ch);
        curl_close($ch);
        throw new TSWriterException($mes);
      }

      $data = curl_getinfo($ch);
      if ($data['http_code'] >= 400)
        throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
      curl_close($ch);
    }
  }

  /**
   * Helper method will list the contents of a given bucket under a
   * given prefix, which may be a directory. The third argument will
   * only include in the return set the objects directly underneath
   * the prefix, that is any objects that share the prefix and whose
   * filename remainder contains no more slashes.
   *
   * @param Site $dept the site
   * @param String $prefix the prefix to fetch
   * @param boolean $recursive true (default) to get EVERY subobject
   * @param String $marker used internally to split into multiple requests
   */
  private function listobjects($prefix, $recursive = true, $marker = null) {
    $params = array(
      'list-type' => '2',
      'prefix' => $prefix,
    );
    if ($recursive === false)
      $params['delimiter'] = '/';
    if ($marker !== null)
      $params['marker'] = $marker;

    $reqHeaders = array(
      'Host' => $this->bucket . '.' . $this->host_base,
      'X-Amz-Content-sha256' => self::EMPTY_BODY_SHA256,
    );

    $request = (new AwsRequest(self::S3_SERVICE_NAME, $this->awsRegion))
      ->withMethod(AwsRequest::METHOD_GET)
      ->withUri('/')
      ->withQueryParams($params)
      ->withHeaders($reqHeaders);
    $this->signRequest($request);

    $ch = $this->prepRequest('/?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request->curlHeaders());

    if (($output = curl_exec($ch)) === false) {
      $mes = curl_error($ch);
      curl_close($ch);
      throw new TSWriterException($mes);
    }

    $data = curl_getinfo($ch);
    if ($data['http_code'] >= 400)
      throw new TSWriterException(sprintf("HTTP error %s: %s", $data['http_code'], $output));
    curl_close($ch);

    // Parse output
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($output);
    if (!$doc) {
      $errors = "";
      foreach(libxml_get_errors() as $error)
        $errors .= '@' . $error->line . ',' . $error->column . ': ' . $error->message . "\n";
      throw new TSWriterException("Invalid response: $errors");
    }

    // Step through XML
    $list = array();
    foreach ($doc->Contents as $sub)
      $list[] = '/' . $sub->Key;

    // Fetch the remaining
    if ((string)$doc->IsTruncated == 'true') {
      foreach ($this->listobjects($prefix, $recursive, substr($list[count($list) - 1], 1)) as $sub)
        $list[] = $sub;
    }

    return $list;
  }

  private function signRequest(AwsRequest $request) {
    $awsCreds = $this->aws_creds_provider->getCredentials();
    $awsSigner = new Aws4Signer($awsCreds);
    $awsSigner->signRequest($request);
  }

  private function toS3Key($fname) {
    if ($this->stripIndexHtml) {
      return preg_replace('/\/index\.html$/', '/', $fname);
    }

    return $fname;
  }
}
