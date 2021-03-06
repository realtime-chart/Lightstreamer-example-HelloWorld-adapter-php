<?php
/*
 * Copyright 2015 Weswit Srl
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace lightstreamer\adapters\remote;

class MpnGcmSubscriptionInfo extends MpnSubscriptionInfo
{

    public function __construct(MpnDeviceInfo $device, $trigger, $collapseKey, $data, $delayWhileIdle, $timeToLive)
    
    {
        parent::__construct($device, $trigger);
        $this->data["collapseKey"] = $collapseKey;
        $this->data["data"] = $data;
        $this->data["delayWhileIdle"] = $delayWhileIdle;
        $this->data["timeToLive"] = $timeToLive;
    }
    
    public function __toString() {
        return sprintf("%s\n\tCollpaseKey=[%s]\n\tData=[%s]\n\tDelayWhileIdle=[%s]\n\tTimeToLive=[%d]\n",
            parent::__toString(),
            $this->data["collapseKey"],
            implode("|", $this->data["data"]),
            $this->data["delayWhileIdle"],
            $this->data["timeToLive"]);
    }
}
?>