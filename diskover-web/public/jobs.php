<?php
chdir(dirname(__FILE__));
require '../vendor/autoload.php';
require '../src/diskover/version.php';

use diskover\Job;
use diskover\JobDatabase;

function create_job($opts)
{
	$job = new Job();
	$job->type = "diskover";
	$job->command = $opts["c"];
	if (isset($opts["d"]))
		$job->description = $opts["d"];
	if (isset($opts["u"]))
		$job->user = $opts["u"];
	else
		$job->user = "root";
	if (isset($opts["k"]))
		$job->credentials = $opts["k"];

	// Load database and find jobs.
	$db = new JobDatabase();
	$db->connect();
	print $db->addJob($job) ."\n";
}

function list_jobs($opts)
{
	// Load database and find jobs.
	$db = new JobDatabase();
	$db->connect();
	$alljobs = $db->getJobs($opts["l"]);
	foreach ($alljobs as $job) {
		$out = "";
		foreach ($job as $k => $v) {
			$out .= "$k:\"$v\",";
		}
		echo substr($out,0,-1). "\n";
	}
}

function update_job($opts)
{
	// Load database and find jobs.
	$db = new JobDatabase();
	$db->connect();
	$alljobs = $db->getJobs($opts["m"]);
	$job = $alljobs[0];

	if (isset($opts{"p"})) {
		$job->crontab = $opts{"p"};
		$db->updateJobCrontab($job);
		return;
	}

	$job->indexName = $opts{"i"};
	if (isset($opts{"e"})) {
		$job->error = 1;
		$job->errorDescription = $opts{"e"};
	}

	$db->updateJob($job);
}

function update_job_policy($opts)
{
	// Load database and find jobs.
	$db = new JobDatabase();
	$db->connect();
	$alljobs = $db->getJobs($opts["m"]);
	$job = $alljobs[0];

	$job->indexName = $opts{"i"};
	if (isset($opts{"e"})) {
		$job->error = 1;
		$job->errorDescription = $opts{"e"};
	}

	$db->updateJob($job);
}

function delete_job($opts)
{
	// Load database and find jobs.
	$db = new JobDatabase();
	$db->connect();
	$db->deleteJob($opts["r"]);
}

function usage()
{
	global $argv;

	die("Usage: $argv[0] -c <cmd-script> [ -d description ] [-k credentials ] [ -u user ]\n" .
		"$argv[0] -r <job-id>\n" .
		"$argv[0] -m <job-id> -i <esindex> -e error\n" .
		"$argv[0] -l <jobi-id|*>\n");
}



$opts = getopt("c:d:m:r:u:i:l:e:p:k:");

if (isset($opts["c"])) {
	if (isset($opts["m"]) or isset($opts["r"]) or isset($opts["i"]))
		usage();

	create_job($opts);
} elseif (isset($opts["r"])) {
	if (isset($opts["c"]) or isset($opts["m"]) or isset($opts["i"]))
		usage();
	delete_job($opts);
} elseif (isset($opts["m"])) {
	if (isset($opts["c"]) or isset($opts["r"]) or (!isset($opts["i"]) and
			!isset($opts["p"])))
		usage();

	update_job($opts);
} elseif (isset($opts["l"])) {
	list_jobs($opts);
} else {
	usage();
}

?>
