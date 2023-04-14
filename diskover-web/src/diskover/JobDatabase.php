<?php
/*
diskover-web community edition (ce)
https://github.com/diskoverdata/diskover-community/
https://diskoverdata.com

Copyright 2017-2022 Diskover Data, Inc.
"Community" portion of Diskover made available under the Apache 2.0 License found here:
https://www.diskoverdata.com/apache-license/

All other content is subject to the Diskover Data, Inc. end user license agreement found at:
https://www.diskoverdata.com/eula-subscriptions/

Diskover Data products and features for all versions found here:
https://www.diskoverdata.com/solutions/

*/

// diskover-web community edition (ce) sqlite3 user database

namespace diskover;
use SQLite3;
require 'config_inc.php';

class JobDatabase
{
    private $db;
    private $databaseFilename;

    public function connect()
    {
        require 'config_inc.php';
        // Get datbase file path from config
        $this->databaseFilename = $config->DATABASE;

        try {
            // Open sqlite database
            $this->db = new SQLite3($this->databaseFilename);
            $this->db->busyTimeout(5000);
        }
        catch (\Exception $e) {
            throw new \Exception('There was an error connecting to the database! ' . $this->databaseFilename. $e->getMessage());
        }

        // Check database file is writable
        if (!is_writable($this->databaseFilename)) {
            throw new \Exception($this->databaseFilename . get_current_user() . getcwd().' is not writable!');
        }

        // Initial setup if necessary.
        $this->setupDatabase();
    }

    protected function setupDatabase()
    {
        require 'config_inc.php';
        // If the database users table is not empty, we have nothing to do here.
        $res = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='jobs'");
        if ($row = $res->fetchArray()) {
            return;
        }

        // Set up sqlite user table if does not yet exist.
        $res = $this->db->exec("CREATE TABLE IF NOT EXISTS jobs (
            job_id varchar(50) PRIMARY KEY,
            type varchar(128) NOT NULL,
            description varchar(250) DEFAULT NULL,
            job varchar(512) NOT NULL,
            cronuser varchar(128) DEFAULT NULL,
            credentials varchar(250) DEFAULT NULL,
            cronjob varchar(250) DEFAULT NULL,
            created_time datetime NOT NULL,
            completed_time datetime DEFAULT NULL,
            esindex varchar(512) DEFAULT NULL,
            error int(4),
            error_string text
            )");

        if (!$res) {
            throw new \Exception('There was an error creating jobs table!');
        }

    }


    protected function closeDatabase()
    {
        $this->db->close();
    }

    public function addJob(Job $job)
    {
        $jobid = uniqid();
        $date = date("Y-m-d H:i:s");

        $this->db->exec("INSERT INTO jobs 
                VALUES ('$jobid', '$job->type', '$job->description',
                        '$job->command', '$job->user',
			'$job->credentials', '$job->crontab',
                        '$date',NULL,NULL,0,NULL)");
	return $jobid;
    }

    public function getJobs($jobid): array
    {

        $alljobs = array();
	$query = 'SELECT * FROM jobs ';
	if (isset($jobid) && ($jobid != '*' and $jobid != 'all'))
		$query .= "WHERE job_id = '$jobid'";
        $res = $this->db->query($query);

        while ($row = $res->fetchArray()) {
            $job = new Job();
            $job->id = $row['job_id'];
            $job->type = $row['type'];
            $job->description = $row['description'];
            $job->command = $row['job'];
            $job->createdTime = $row['created_time'];
            $job->indexName = $row['esindex'];
            $job->completedTime = $row['completed_time'];
            $job->user = $row['cronuser'];
            $job->crontab = $row['cronjob'];
            $job->credentials = $row['credentials'];
            $job->error = $row['error'];

            $alljobs[] = $job;
        }

        return $alljobs;
    }

    /**
     * Does not support changing username.
     * Only update is password hash.
     *
     * @throws \Exception
     */
    public function updateJob(Job $job)
    {
        $foundJob = false;

        $res = $this->db->exec("UPDATE jobs SET esindex='$job->indexName',
                error='$job->error', error_string='$job->errorDescription',
                completed_time='$job->completedTime'
                    WHERE job_id = '$job->id'");

        if ($res) {
            $foundJob = true;
        }

        if (!$foundJob) {
            throw new \Exception('Tried to update a non-existent Job.');
        }
    }

    public function updateJobCrontab(Job $job)
    {
        $foundJob = false;

        $res = $this->db->exec("UPDATE jobs SET cronjob='$job->crontab'
                    WHERE job_id = '$job->id'");

        if ($res) {
            $foundJob = true;
        }

        if (!$foundJob) {
            throw new \Exception('Tried to update a non-existent Job.');
        }
    }

    public function deleteJob($jobid)
    {

	$query = "DELETE FROM jobs WHERE job_id = '$jobid'";
        $res = $this->db->query($query);

        if (!$res) {
            throw new \Exception('Tried to delete a non-existent Job.');
        }
    }
}
