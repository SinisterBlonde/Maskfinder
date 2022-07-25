<?php
declare(strict_types=1);

const ViewerLimit = 30;
const MaskLimit   = 1000;

const Keywords = [
    "impressum",
    "merch",
    "shop",
    "tts",
    "texttospeech"
];

$Pointer = \curl_init();
\curl_setopt($Pointer, \CURLOPT_SSL_VERIFYPEER, 0);
\curl_setopt($Pointer, \CURLOPT_SSL_VERIFYHOST, 0);
\curl_setopt($Pointer, \CURLOPT_RETURNTRANSFER, 1);

//Fetch Client-ID.
\curl_setopt($Pointer, \CURLOPT_URL, "https://www.twitch.tv/directory/game/Just%20Chatting");
$Response = \curl_exec($Pointer);
[$_, $Response] = \explode("clientId=\"", $Response);
[$ID, $_] = \explode("\",commonOptions", $Response);

//Crawl masks.
\curl_setopt($Pointer, \CURLOPT_URL, "https://gql.twitch.tv/gql");
\curl_setopt($Pointer, \CURLOPT_HTTPHEADER, ["Client-ID: $ID"]);
\curl_setopt($Pointer, \CURLOPT_POST, 1);
$Cursor         = "null";
$PotentialMasks = [];

for($i = 0; $i <= MaskLimit; $i += 30) {

    //Set parameters.
    \curl_setopt(
        $Pointer,
        \CURLOPT_POSTFIELDS,
        '[{
            "operationName": "DirectoryPage_Game",
            "variables":     {
                "imageWidth":          50,
                "name":                "just chatting",
                "options": {
                    "tags": ["9166ad14-41f1-4b04-a3b8-c8eb838c6be6"]
                },
                "freeformTagsEnabled": false,
                "sortTypeIsRecency":   false,
                "limit":               30,
                "cursor":              ' . $Cursor . '
            },
            "extensions": {
                "persistedQuery": {
                    "version":    1,
                    "sha256Hash": "749035333f1837aca1c5bae468a11c39604a91c9206895aa90b4657ab6213c24"
                }
            }
        }]'
    );

    //Fetch streamers.
    $Response = \json_decode(\curl_exec($Pointer), true);

    if(\count($Response[0]["data"]["game"]["streams"]["edges"]) === 0) {
        break;
    }

    //Filter big streamers.
    foreach($Response[0]["data"]["game"]["streams"]["edges"] as $Stream) {
        if($Stream["node"]["viewersCount"] <= ViewerLimit) {
            $PotentialMasks[$Stream["node"]["broadcaster"]["id"]] = $Stream["node"]["broadcaster"]["login"];
        }
        $Cursor = '"' . $Stream["cursor"] . '"';
    }

}

echo "Gathered " . \count($PotentialMasks) . " potential masks. \r\n";
\flush();

\curl_setopt($Pointer, \CURLOPT_POST, 0);
$Masks = [];

//Parse info of potential masks.
foreach($PotentialMasks as $ID => $Mask) {

    //Fetch channel info.
    \curl_setopt(
        $Pointer,
        \CURLOPT_POSTFIELDS,
        '[
            {
                "extensions":    {
                    "persistedQuery": {
                        "sha256Hash": "6089531acef6c09ece01b440c41978f4c8dc60cb4fa0124c9a9d3f896709b6c6",
                        "version":    1
                    }
                },
                "operationName": "ChannelRoot_AboutPanel",
                "variables":     {
                    "channelLogin": "' . $Mask . '",
                    "skipSchedule": false
                }
            },
            {
                "operationName": "ChannelPanels",
                "variables":     {"id": "' . $ID . '"},
                "extensions":    {
                    "persistedQuery": {
                        "version":    1,
                        "sha256Hash": "c388999b5fcd8063deafc7f7ad32ebd1cce3d94953c20bf96cffeef643327322"
                    }
                }
            }
        ]'
    );

    [$Info, $Panels] = \json_decode(\curl_exec($Pointer), true);

    $Tags = [];

    //Parse description.
    if($Info["data"]["user"]["description"] !== null) {
        $Description = \strtolower($Info["data"]["user"]["description"]);
        foreach(Keywords as $Keyword) {
            if(\str_contains($Description, $Keyword) && !\in_array($Keyword, $Tags)) {
                $Tags[] = $Keyword;
            }
        }
    }

    //Parse panels.
    foreach($Panels["data"]["user"]["panels"] as $Panel) {

        //Skip empty panels.
        if(!isset($Panel["linkURL"]) && !isset($Panel["description"])) {
            continue;
        }

        foreach(Keywords as $Keyword) {
            if(\str_contains($Panel["linkURL"] ?? "", $Keyword) && !\in_array($Keyword, $Tags)) {
                $Tags[] = $Keyword;
            }
            if(\str_contains(\strtolower($Panel["description"] ?? ""), $Keyword) && !\in_array($Keyword, $Tags)) {
                $Tags[] = $Keyword;
            }
        }

    }

    //Do we have a mask?
    if(\count($Tags) > 0) {
        $Masks[$Mask] = $Tags;
    }

}

echo "<pre>";
echo json_encode($Masks, JSON_PRETTY_PRINT);
echo "</pre>";
echo "<h1>Die NWO sieht Alles!</h1>";