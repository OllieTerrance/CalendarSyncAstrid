<?
require("../bin/keystore.php");
function req($method, $args) {
    $url = "https://astrid.com/api/7/" . $method . "?";
    $sig = $method;
    $args["app_id"] = keystore("astrid", "id");
    $args["time"] = time();
    ksort($args);
    foreach ($args as $key => $value) {
        $sig .= $key . $value;
    }
    $sig .= keystore("astrid", "secret");
    $args["sig"] = md5($sig);
    $vars = http_build_query($args);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    return json_decode(curl_exec($ch));
}
function dateStr($epoch, $offset=0) {
    return strftime("%Y%m%dT%H%M%S", intval($epoch) + $offset * 60 * 60);
}
function page($content, $indent=true) {
    print('<html>
    <head>
        <title>Calendar Sync for Astrid</title>
        <link rel="icon" type="image/png" href="astrid.png"/>
    </head>
    <body>
        <h1>Calendar Sync for Astrid</h1>
        ' . ($indent ? preg_replace("/\n/", "\n        ", $content) : $content) . '
    </html>
</body>');
}
if ($_GET["token"]) {
    $tasks = req("task_list", array("token" => $_GET["token"]));
    if ($tasks->status === "success") {
        $events = array();
        $head = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Ollie Terrance//Calendar Sync for Astrid//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Astrid Tasks
X-WR-CALDESC:Tasks from your Astrid account.';
        $offset = intval($_GET["offset"]);
        foreach ($tasks->list as $i => $task) {
            if ($task->has_due_time) {
                $event = array("BEGIN" => "VEVENT",
                               "DTSTART" => dateStr($task->due, $offset),
                               "DTEND" => dateStr($task->due, $offset),
                               "UID" => $task->uuid . ($_GET["i"] ? "+" . $_GET["i"] : ""),
                               "CREATED" => dateStr($task->created_at, $offset),
                               "DESCRIPTION" => preg_replace("/,/", "\\,", preg_replace("/\n/", "\\n", $task->notes)),
                               "LAST-MODIFIED" => dateStr($task->updated_at, $offset),
                               "SUMMARY" => preg_replace("/,/", "\\,", $task->title),
                               "END" => "VEVENT");
                $eventMerged = array();
                foreach ($event as $key => $item) {
                    $eventMerged[] = $key . ":" . $item;
                }
                $events[] = implode("\n", $eventMerged);
            }
        }
        $tail = "END:VCALENDAR";
        $cal = $head . "\n" . implode("\n", $events) . "\n" . $tail;
        if ($_GET["ical"]) {
            header('Content-type: text/calendar');
            header('Content-Disposition: attachment; filename="astrid.ical"');
            print($cal);
        } else {
            page('<p>This is your generated calendar in plain text format.  Click <a href="?token=' . $_GET["token"] . '&ical=1">here</a> to download it as an iCal file.</p>
        <pre>' . $cal . '</pre>', false);
        }
    } else {
        page('<p>An error was returned by Astrid:</p>
<code>' . $tasks->code . ': ' . $tasks->message . '</code>');
    }
} elseif ($_POST["submit"]) {
    $user = req("user_signin", array("email" => $_POST["user"],
                                     "provider" => "password",
                                     "secret" => $_POST["pass"]));
    if ($user->status === "success") {
        page('<p><strong>Success!</strong>  Your token is <code>' . $user->token . '</code>, and your calendar is <a href="?token=' . $user->token . '&ical=1">here</a> (or in plain text <a href="?token=' . $user->token . '">here</a>).</p>
<p>In order to keep your calendar up-to-date, you will need to subscribe to it using the following URL:</p>
<code>http://' . $_SERVER[HTTP_HOST] . $_SERVER[REQUEST_URI] . '?token=' . $user->token . '&ical=1</code>
<p>If the tasks appear on your calendar in the wrong time zone (i.e. at the wrong hour), you can override this by adding <code>&offset=X</code> to the end of the URL, where X is the number of hours to adjust by.  Decimal values are allowed, and to reduce the time, use a negative offset.</p>
<p><em>Please note that this app is in alpha &ndash; things are likely to break, and the URL is liable to change.</em></p>');
    } else {
        page('<p>An error was returned by Astrid:</p>
<code>' . $user->code . ': ' . $user->message . '</code>');
    }
} else {
    page('<p>This tool will generate you an iCal format calendar with all your Astrid tasks that have a due date/time.</p>
<p>To start, you need to login with your Astrid account.  Your details are <strong>not</strong> stored on this server, you will just be given a token as provided by the Astrid API to fetch your tasks.</p>
<form method="post">
    <label for="user">Username:</label>
    <input id="user" name="user"/>
    <br/>
    <label for="pass">Password:</label>
    <input id="pass" name="pass" type="password"/>
    <br/><br/>
    <input name="submit" type="submit" value="Get Token"/>
</form>');
}
?>