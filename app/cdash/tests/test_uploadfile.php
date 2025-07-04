<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Utils\SubmissionUtils;
use Illuminate\Support\Facades\Storage;

class UploadFileTestCase extends KWWebTestCase
{
    public $BuildId;
    public $FileId;
    public $Sha1Sum;

    public function __construct()
    {
        parent::__construct();
    }

    // Submit an upload XML
    public function testSubmitUploadXML()
    {
        $this->deleteLog($this->logfilename);
        $rep = dirname(__FILE__) . '/data/EmailProjectExample';
        $file = "$rep/1_upload.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            $this->fail('Failed to submit Upload.xml');
            return;
        }
        if (!$this->checkLog($this->logfilename)) {
            $this->fail('errors in log file');
            return;
        }
    }

    // Make sure the uploaded files are present
    public function testVerifyFileSubmission()
    {
        $this->deleteLog($this->logfilename);
        // Verify file exists in the database
        $query = $this->db->query('SELECT buildid, fileid FROM build2uploadfile');
        if (count($query) == 0) {
            $this->fail('No build2upload records were added to the database');
            return;
        }
        $this->BuildId = $query[0]['buildid'];

        // Verify content exists on disk
        $query = $this->db->query("SELECT id, sha1sum FROM uploadfile WHERE filename='CMakeCache.txt'");
        if (count($query) == 0) {
            $this->fail('CMakeCache.txt was not added to the uploadfile table');
            return;
        }
        $this->FileId = $query[0]['id'];
        $this->Sha1Sum = $query[0]['sha1sum'];

        $uploaded_filepath = "upload/{$this->Sha1Sum}";
        if (!Storage::exists($uploaded_filepath)) {
            $this->fail("File was not written to $uploaded_filepath");
            return;
        }

        // Make sure we can download the file and its contents don't change
        // during the download.
        $url = "{$this->url}/build/{$this->BuildId}/file/{$this->FileId}";
        $tmp_file = sys_get_temp_dir() . '/CMakeCache.txt';

        $client = $this->getGuzzleClient();
        $response = $client->request('GET', $url);
        $body = $response->getBody();
        file_put_contents($tmp_file, $body);

        if (filesize($tmp_file) !== Storage::size($uploaded_filepath)) {
            $this->fail('filesize mismatch for downloaded file');
        }
        if (md5_file($tmp_file) !== SubmissionUtils::hashFileHandle(Storage::readStream($uploaded_filepath), 'md5')) {
            $this->fail("hash mismatch for downloaded file ($tmp_file) vs ($uploaded_filepath)");
        }

        unlink($tmp_file);
    }

    // Make sure the build label has been set
    public function testVerifyLabel()
    {
        $this->get($this->url . '/api/v1/index.php?project=EmailProjectExample&date=2009-02-23&filtercount=1&showfilters=1&field1=label&compare1=63&value1=UploadBuild');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $build = array_pop($buildgroup['builds']);

        // Verify label
        if ($build['label'] !== 'UploadBuild') {
            $this->fail('Expected UploadBuild, found ' . $build['label']);
        }
    }
}
