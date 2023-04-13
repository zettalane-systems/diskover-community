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

// diskover-web community edition (ce) user data

namespace diskover;

class Job
{
    public $id;

    public $type;
    public $description;

    public $command;
    public $user;
    public $credentials;
    public $crontab;
    public $createdTime;
    public $completedTime;
    public $indexName;
    public $error;
    public $errorDescription;


}

