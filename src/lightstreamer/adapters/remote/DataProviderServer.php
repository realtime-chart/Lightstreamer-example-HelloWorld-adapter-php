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

class DataProviderServer extends Server implements ItemEventListener
{

    private $dataAdapter;

    public function __construct(IDataProvider $dataAdapter)
    {
        $this->dataAdapter = $dataAdapter;
        $this->setReplyHandler(new DefaultReplyHandler());
        $this->setNotifyHandler(new DefaultNotifyHandler());
    }

    protected function putActiveItem($requestId, $itemName)
    {
        $this[$itemName] = $requestId;
    }

    protected function getActiveItem($itemName)
    {
        if (isset($this[$itemName])) {
            return $this[$itemName];
        } else {
            echo "No active item found for request [$itemName]!\n";
            return NULL;
        }
    }

    protected function removeActiveItem($itemName)
    {
        if (isset($this[$itemName])) {
            unset($this[$itemName]);
        } else {
            echo "No active item found for [$itemName]!\n";
        }
    }

    protected function doNotify($data)
    {
        $timestamp = strval(round(microtime(true) * 1000));
        $notifyString = "$timestamp|$data\n";
        $this->sendNotify($notifyString);
    }

    public function update($itemName, $eventsMap, $isSnapshot)
    {
        $requestId = $this->getActiveItem($itemName);
        
        if (! is_null($requestId)) {
            $snapshotFlag = RemoteProtocol::encodeBoolean($isSnapshot);
            $qry = "UD3|S|$itemName|S|$requestId|B|$snapshotFlag";
            foreach ($eventsMap as $field_name => $field_value) {
                $enc_field_name = RemoteProtocol::encodeString($field_name);
                $enc_field_value = RemoteProtocol::encodeString($field_value);
                $qry .= "|S|$enc_field_name|S|$enc_field_value";
            }
            $this->doNotify($qry);
        } else {
            echo "Unexpected update for item [$itemName]!\n";
        }
    }

    public function clearSnapshot($itemName)
    {
        $requestId = $this->getActiveItem($itemName);
        
        if (! is_null($requestId)) {
            $notify = DataProviderProtocol::writeCLS($itemName, $requestId);
            $this->doNotify($notify);
        } else {
            echo "Unexpected clearSnapshot for item [$itemName]!\n";
        }
    }

    public function endOfSnapshot($itemName)
    {
        $requestId = $this->getActiveItem($itemName);
        if (! is_null($requestId)) {
            $notify = DataProviderProtocol::writeEOS($itemName, $requestId);
            $this->doNotify($notify);
        } else {
            echo "Unexpected endOfSnapshot for item [$itemName]!\n";
        }
    }

    public function failure(\Exception $exception)
    {
        $notify = DataProviderProtocol::writeFailure($exception);
        $this->doNotify($notify);
    }

    public function onDPI($requestId, $data)
    {
        $params = DataProviderProtocol::readInit($data);
        $this->dataAdapter->init($params);
        $this->dataAdapter->setListener($this);
        $response = DataProviderProtocol::writeInit();
        
        return $response;
    }

    public function onSUB($requestId, $data)
    {
        $itemName = DataProviderProtocol::readSub($data);
        $this->putActiveItem($requestId, $itemName);
        $snapshotAvailable = $this->dataAdapter->isSnapshotAvailable($itemName);
        
        if (! $snapshotAvailable) {
            $this->endOfSnapshot($itemName, $requestId);
        }
        
        $response = "";
        try {
            $this->dataAdapter->subscribe($itemName);
            $response = DataProviderProtocol::writeSub();
        } catch (\Exception $e) {
            $response = DataProviderProtocol::writeSubWithException($e);
        }
        
        return $response;
    }

    public function onUSB($requestId, $data)
    {
        $itemName = DataProviderProtocol::readUnsub($data);
        $response = "";
        try {
            
            $this->dataAdapter->unsubscribe($itemName);
            $this->removeActiveItem($itemName);
            $response = DataProviderProtocol::writeUnsub();
        } catch (\Exception $e) {
            $response = DataProviderProtocol::writeUnsubWithException($e);
        }
        
        return $response;
    }

    public function onReceivedRequest($request)
    {
        $parsed_request = RemoteProtocol::parse_request($request);
        $requestId = $parsed_request["id"];
        $method = $parsed_request["method"];
        $data = $parsed_request["data"];
        
        $onFunction = "on$method";
        $response = $this->$onFunction($requestId, $data);
        
        if ($response) {
            $replyString = "$requestId|$response\n";
            $this->sendReply($replyString);
        }
    }
}

?>