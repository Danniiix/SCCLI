<?php

require_once("SC-API/src/snapchat.php");

const AUTH_DATA_FOLDER = "auth";

echo "Interactive Snap-API\n";

$config = new Configuration();
$friendsDB = null;

$keepRunning = true;
$stdin = fopen('php://stdin', 'r');
$availableCommands = array("close", "get", "write", "fetch", "send", "friend", "sync", "set", "login", "snaps", "stories", "output", "add", "db");
$simpleCommands	   = array("close", "login", "write");
$aliases 		   = array("friends" => "friend",
						   "exit"    => "close",
						   "snap"	 => "snaps",
						   "story"	 => "stories",
						   "print"   => "output");

if ($argc > 1) {
	if ($argv[1] == "offline") {
		$config->offline = true;
		echo "Snap-API is in offline mode. Restart the script to go back online.\n";
	}
}

if (isset($config->username)) {
	$snapchat = new Snapchat($config->username, $config->gEmail, $config->gPasswd, AUTH_DATA_FOLDER, $config->debug, $config->cli);
	$snapchat->login($config->password, $config->auth_token, $config->noAppOpenEvent, $config->forceLogin);
	$friendsDB = new FriendsDatabase($snapchat);
} else {
	echo "No username specified. Please login manually using the login command.\n";
}

while ($keepRunning) {
	echo ">> ";
	$userInput = fgets($stdin);
	$userInput = trim($userInput);
	$params = explode(" ", $userInput);

	if (in_array($params[0], $availableCommands)) {
		if (in_array($params[0], $simpleCommands)) {
			$params[0]($params);
		} else {
			$params[0]($params[1], $params);
		}
	} elseif (array_key_exists($params[0], $aliases)) {
		if (in_array($aliases[$params[0]], $simpleCommands)) {
			$aliases[$params[0]]($params);
		} else {
			$aliases[$params[0]]($params[1], $params);
		}
	} else {
		echo "$params[0] is not a valid command.\n";
	}
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////


function close($params) {
	global $keepRunning;
	$keepRunning = false;
}

function login($params) {
	global $snapchat, $config, $friendsDB;

	if (count($params) == 2)
		$config->username 	= $params[1];

	if (count($params) == 3)
		$config->auth_token	= ((count($params) == 2) ? "" : (($params[2] == "auth-token") ? $params[3] : ""));

	$snapchat = new Snapchat($config->username, $config->gEmail, $config->gPasswd, AUTH_DATA_FOLDER, $config->debug, $config->cli);
	$snapchat->login($config->password, $config->auth_token, $config->noAppOpenEvent, $config->forceLogin);
	$friendsDB = new FriendsDatabase($snapchat);
}

function set($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'location':
			if (count($params) < 3) {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}
			$snapchat->setLocation($params[2], $params[3]);
			break;

		default:
			echo "'$subcommand' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function get($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'snaps':
			if (count($params) < 3) {
				$snapchat->getSnaps(true);
			} else {
				foreach (compileList(array_slice($params, 2)) as $friend) {
					if (in_array($friend, $snapchat->getFriends())) {
						$snapchat->getSnapsByUsername($friend, true);
					} else {
						echo "$friend is not your friend.\n";
					}
				}
			}
			break;

		case 'snap':
			if (count($params) > 2) {
				foreach (array_slice($params, 2) as $snap) {
					$snapchat->getMedia($snap);
				}
			} else {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}
			break;

		case 'snaptag':
			$snapchat->getSnaptag(true);
			break;

		case 'stories':
			if (count($params) >= 3) {
				foreach (compileList(array_slice($params, 2)) as $friend) {
					if (in_array($friend, $snapchat->getFriends())) {
						$snapchat->getStoriesByUsername($friend, true);
					} else {
						echo "$friend is not your friend.\n";
					}
				}
			} elseif (count($params) == 2) {
				$snapchat->getMyStories(true);
			}
			break;

		case 'story':
			if (count($params) == 2) {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}
			$friendStories = $snapchat->getFriendStories();
			foreach ($friendStories as $story) {
				if ($story->media_id == $params[2]) {
					echo "Downloading story '{$story->media_id}' from '{$story->username}'... ";
					$snapchat->getStory($story->media_id, $story->media_key, $story->media_iv, $story->username, $story->timestamp, true);
					echo " done!\n";
					break;
				}
			}
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function snaps($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'get':
			get('snaps', $params);
			break;

		case 'adjust':

			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function stories($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'get':
			get('stories', $params);
			break;

		case 'renew':

			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function fetch($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'updates':
			$snapchat->getUpdates(true);
			break;

		case 'conversations':
		case 'convos':
			$snapchat->getConversations(true);
			echo " done!\n";
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function write($params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	foreach (array_slice($params, 1) as $subcommand) {
		switch ($subcommand) {
			case 'updates':
				echo "Writing updates.txt...";
				$data = $snapchat->getUpdates();
				file_put_contents("updates.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'friends':
				echo "Writing friends.txt...";
				$data = $snapchat->getFriends();
				file_put_contents("friends.txt", implode("\n", $data));
				echo " done!\n";
				break;

			case 'friends:added':
				echo "Writing friends_added.txt...";
				$data = $snapchat->getAddedFriends();
				file_put_contents("friends_added.txt", implode("\n", $data));
				echo " done!\n";
				break;

			case 'friends:unconfirmed':
				echo "Writing friends_unconfirmed.txt...";
				$data = $snapchat->getUnconfirmedFriends();
				file_put_contents("friends_unconfirmed.txt", implode("\n", $data));
				echo " done!\n";
				break;

			case 'scores':
				$data = $snapchat->getFriendScores($snapchat->getFriends());

				echo "Writing scores.txt...";
				asort($data);

				$lk = max(array_map('strlen', array_keys($data)));
				$lv = max(array_map('strlen', array_values($data)));
				$format = "%' -{$lk}s : %' {$lv}s";
				$datastring = array();
				foreach ($data as $k => $v) {
					$datastring[] = sprintf($format, $k, $v);
				}

				file_put_contents("scores.txt", implode("\n", $datastring));
				echo " done!\n";
				break;

			case 'snaps':
				echo "Writing snaps.txt...";
				$data = $snapchat->getSnaps();
				file_put_contents("snaps.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'stats':
				echo "Collecting data...\n";
				$data = $snapchat->getMyStories();
				$views = array();
				$screens = array();
				foreach ($data as $story) {
					foreach ($story->story_notes as $view) {
						$views[] = $view->viewer;
						if (!empty($view->screenshotted)) $screens[] = $view->viewer;
					}
				}

				if (is_file("stats_views.txt")) {
					$ex_views = explode("\n", file_get_contents("stats_views.txt"));
					$views = array_unique(array_merge($views, $ex_views));
					sort($views);
				}

				if (is_file("stats_screenshots.txt")) {
					$ex_screens = explode("\n", file_get_contents("stats_screenshots.txt"));
					$screens = array_unique(array_merge($screens, $ex_screens));
					sort($screens);
				}

				echo "Writing stats_views.txt and stats_screenshots.txt...";

				file_put_contents("stats_views.txt", implode("\n", $views));
				file_put_contents("stats_screenshots.txt", implode("\n", $screens));
				echo " done!\n";
				break;

			case 'stories':
				echo "Writing stories.txt...";
				$data = $snapchat->getFriendStories();
				file_put_contents("stories.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'stories:own':
				echo "Writing stories_own.txt...";
				$data = $snapchat->getMyStories();
				file_put_contents("stories_own.txt", print_r($data, true));
				echo " done!\n";
				break;

			case (preg_match("/stories:(.*)/", $subcommand, $matches) ? true : false):
				$username = $matches[1];
				echo "Writing stories_$username.txt...";
				$story = $snapchat->getStoriesByUsername($username);
				file_put_contents("stories_$username.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'conversations':
			case 'convos':
				echo "Writing conversations.txt...";
				$data = $snapchat->getConversations();
				file_put_contents("conversations.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'conversations:pending':
			case 'convos:pending':
			case 'convos:p':
				global $username;
				echo "Writing conversations_pending.txt...";
				$data = $snapchat->getConversations();
				$data_tw = array();
				foreach($data as &$conversation) {
					foreach ($conversation->participants as $participant) {
						if ($participant != $username) {
							$friend = $participant;
						}
					}
					if (count($conversation->pending_received_snaps) > 0) {
						$data_tw[] = $friend;
					}
				}
				file_put_contents("conversations_pending.txt", implode("\n", $data_tw));
				echo " done!\n";
				break;

			case 'conversations:friends':
			case 'convos:friends':
			case 'convos:f':
				global $username;
				echo "Writing conversations_friends.txt...";
				$data = $snapchat->getConversations();
				$data_tw = array();
				foreach($data as &$conversation) {
					foreach ($conversation->participants as $participant) {
						if ($participant != $username) {
							$data_tw[] = $participant;
						}
					}
				}
				file_put_contents("conversations_friends.txt", implode("\n", $data_tw));
				echo " done!\n";
				break;

			case 'conversations:list':
			case 'convos:list':
			case 'convos:l':
				global $username;
				echo "Writing conversations_list.txt...";
				$conversations = $snapchat->getConversations();
				$data = array();
				$lp = 0;
				foreach($conversations as &$conversation) {
					foreach ($conversation->participants as $participant) {
						if ($participant != $username) {
							$temp['participant'] = $participant;
							if ($lp < strlen($participant)) $lp = strlen($participant);
						}
					}
					$temp['timestamp'] = $conversation->last_interaction_ts;
					$data[] = (object)$temp;
				}

				$format = "%' -{$lp}s | %s | %s";
				$data_tw = array();
				foreach ($data as $d) {
					$data_tw[] = sprintf($format, $d->participant, date("Y-m-d H-i-s", (int) ($d->timestamp / 1000)), $d->timestamp);
				}

				file_put_contents("conversations_list.txt", implode("\n", $data_tw));
				echo " done!\n";
				break;

			default:
				echo "'$subcommand' is not a valid command for '{$params[0]}'.\n";
				return false;
				break;
		}
	}
}

function output($subcommand, $params) {
		global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'friendmoji':
			$data = $snapchat->getUpdates();
			foreach ($data['data']->friends_response->friends as $friend) {
				if (!empty($friend->friendmoji_string)) echo sprintf("%' 20s -> %s\n", $friend->name, $friend->friendmoji_string);
			}
			break;

		default:
			echo "'$subcommand' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function friend($subcommand, $params) {
	global $snapchat, $friendsDB;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'add':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo "$friend is your friend already.\n";
				} else {
					echo $snapchat->addFriend($friend)."\n";
					$friendsDB->addFriend($friend);
				}
			}
			break;

		case 'delete':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo $snapchat->deleteFriend($friend)."\n";
					$friendsDB->removeFriend($friend);
				} else {
					echo "$friend is not your friend.\n";
				}
			}
			break;

		case 'name':
			if (count($params) <= 2) {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}

			$displayname = implode(" ", array_slice($params, 3));

			if (in_array($params[2], $snapchat->getFriends())) {
				echo $snapchat->setDisplayName($params[2], $displayname)."\n";
				$friendsDB->set($friend, "display", $displayname);
			} else {
				echo "$params[2] is not your friend.\n";
			}
			break;

		case 'block':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo $snapchat->block($friend)."\n";
				} else {
					echo "$friend is not your friend.\n";
				}
			}
			break;

		case 'unblock':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo $snapchat->unblock($friend)."\n";
				} else {
					echo "$friend is not your friend.\n";
				}
			}
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function add($subcommand, $params) {
	global $snapchat, $friendsDB;

	if (count($params) < 2) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	$friend = $params[1];
	$display = null;
	$age = null;
	$location = null;

	if (count($params) > 2)
		$display = implode(" ", array_slice($params, 1));

	if (count($params) == 3)
		$age = $params[2];

	if (count($params) == 4)
		$location = $params[3];

	friend("add", array("friend", "add", $friend));

	if (!is_null($display)) {
		echo $snapchat->setDisplayName($friend, $display)."\n";
	}

	$friendsDB->addFriend($friend, time(), $display, $age, $location);
}

function db($subcommand, $params) {
	global $snapchat, $friendsDB;

	if (count($params) < 2) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	switch ($subcommand) {
		case 'fav':
			if (!$friendsDB->isFriend($params[2])) {
				echo "Friend not found.\n";
				return false;
			}
			$friendsDB->set($params[2], "fav", true);
			break;

		case 'update':
			if (count($params) > 2) {
				switch ($params[2]) {
					case 'all':
						$friendsDB->update();
						$friendsDB->updateScores();
						$friendsDB->updateActiveFriends();
						break;

					case 'scores':
						$friendsDB->updateScores();
						break;

					case 'active':
						$friendsDB->updateActiveFriends();
						break;

					default:
						echo "'$params[2]' is not a valid command for '{$params[0]} {$params[1]}'.\n";
						break;
				}
			} else {
				$friendsDB->update();
			}
			break;

		case "set":
			if (!$friendsDB->isFriend($params[2])) {
				echo "Friend not found.\n";
				return false;
			}
			$friendsDB->set($params[2], $params[3], $params[4]);
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function send($subcommand, $params) {
	global $snapchat;

	if (count($params) < 4) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	$path = $params[2];
	$time = 10;
	$text = null;
	$friends = array();

	if (!file_exists($path)) {
		echo "The specified image does not exist.\n";
		return false;
	}

	$isText = false;
	for ($i=3; $i < count($params); $i++) {
		if (startsWith($params[$i], "time:")) {
			$time = (int)(str_replace("time:", "", $params[$i]));
		} elseif (startsWith($params[$i], "text:'")) {
			$isText = true;
			$text = str_replace("text:'", "", $params[$i]);
		} elseif ($isText) {
			if (endsWith($params[$i], "'")) {
				$text .= " " . str_replace("'", "", $params[$i]);
				$isText = false;
			} else {
				$text .= " " . $params[$i];
			}
		} else {
			if (file_exists($params[$i])) {
				$content = file_get_contents($params[$i]);
				$arr_friends = explode("\n", $content);
				$friends = array_merge($friends, $arr_friends);
			} else {
				$friends[] = $params[$i];
			}
		}
	}

	switch ($subcommand) {
		case 'snap':
			$batchcount = 30;
			$batches = array_chunk($friends, $batchcount);

			$total = count($friends);
			$current = 0;
			foreach ($batches as $batch) {
				$current = $current + count($batch);
				$snapchat->send($path, $batch, $text, $time);
				echo sprintf("Snapping: %' 4d / %' 4d done.\r", $current, $total);
			}
			echo "\n";
			break;

		case 'story':
			$snapchat->setStory($path, $time, $text);
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function sync($subcommand, $commands) {
	global $snapchat;

	if (count($commands) == 1) {
		echo "Not enough arguments for '{$commands[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($commands[1]) {
		case 'snaps':

			break;

		case 'stories':

			break;

		default:
			echo "'$commands[1]' is not a valid command for '{$commands[0]}'.\n";
			return false;
			break;
	}
}

////////////////////////////////////////////////////////////////////////////////////////////////////////

function compileList($arguments) {
	$return = array();
	foreach ($arguments as $arg) {
		if (file_exists($arg)) {
			$content = file_get_contents($arg);
			$arr = explode("\n", $content);
			$return = array_merge($return, $arr);
		} else {
			$return[] = $arg;
		}
	}
	return array_filter(array_unique($return, SORT_STRING));
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

class JSON
{
	static function prettify($json)
	{
		$json = json_encode($json);

	    $result = '';
	    $level = 0;
	    $in_quotes = false;
	    $in_escape = false;
	    $ends_line_level = NULL;
	    $json_length = strlen($json);

	    for( $i = 0; $i < $json_length; $i++ ) {
	        $char = $json[$i];
	        $new_line_level = NULL;
	        $post = "";
	        if( $ends_line_level !== NULL ) {
	            $new_line_level = $ends_line_level;
	            $ends_line_level = NULL;
	        }
	        if ( $in_escape ) {
	            $in_escape = false;
	        } else if( $char === '"' ) {
	            $in_quotes = !$in_quotes;
	        } else if( ! $in_quotes ) {
	            switch( $char ) {
	                case '}': case ']':
	                    $level--;
	                    $ends_line_level = NULL;
	                    $new_line_level = $level;
	                    break;

	                case '{': case '[':
	                    $level++;
	                case ',':
	                    $ends_line_level = $level;
	                    break;

	                case ':':
	                    $post = " ";
	                    break;

	                case " ": case "\t": case "\n": case "\r":
	                    $char = "";
	                    $ends_line_level = $new_line_level;
	                    $new_line_level = NULL;
	                    break;
	            }
	        } else if ( $char === '\\' ) {
	            $in_escape = true;
	        }
	        if( $new_line_level !== NULL ) {
	            $result .= "\n".str_repeat( "\t", $new_line_level );
	        }
	        $result .= $char.$post;
	    }

	    return $result;
	}
}

class Configuration
{
	public function __get($k)
	{
		$values = json_decode(file_get_contents("config.json"), true);

		if (isset($values[$k]))
			return $values[$k];
		else
			return null;
	}

	public function __set($k, $v)
	{
		$values = json_decode(file_get_contents("config.json"), true);
		$values[$k] = $v;
		file_put_contents("config.json", JSON::prettify($values));
	}

	public function __isset($k)
	{
		$values = json_decode(file_get_contents("config.json"), true);
		return isset($values[$k]);
	}
}

class FriendsDatabase
{

	const DATA_FILE = "friends.json";
	const DATA_DIR  = "data";

	private $snapchat = null;
	private $_friends = array();

	private $status = [
		0 => "confirmed",
		1 => "unconfirmed",
		2 => "blocked",
		3 => "deleted"
	];

	public function __construct($snapchat)
	{
		$this->snapchat = $snapchat;
		$friendList = $this->snapchat->getFriends(false);
		$friendData = $this->snapchat->getFriends(true);

		if (is_file(self::DATA_FILE))
			$this->_friends = json_decode(file_get_contents(self::DATA_FILE), true);
	}

	public function update()
	{
		$snapchatData 		= $this->snapchat->getUpdates();
		$friendData 		= $this->snapchat->getFriends(true);
		$addedFriendsData 	= $this->snapchat->getAddedFriends(true);

		foreach ($friendData as $friend) {
			if (!$this->isFriend($friend->name)) {
				$this->_friends[$friend->name] = ["name" => $friend->name];
			}
			if (array_key_exists($friend->name, $addedFriendsData)) {
				$this->_friends[$friend->name]["added_timestamp"] = round($addedFriendsData[$friend->name]->ts / 1000);
				$this->_friends[$friend->name]["added_timestamp_r"] = date("d.m.Y H:i:s", $this->_friends[$friend->name]["added_timestamp"]);
			}
			$this->_friends[$friend->name]["display"] = $friend->display;
			$this->_friends[$friend->name]["type"] = $friend->type;
			$this->_friends[$friend->name]["type_r"] = $this->status[$friend->type];

			ksort($this->_friends[$friend->name]);
		}

		foreach ($this->_friends as $friend) {
			if (!array_key_exists($friend['name'], $friendData)) {
				unset($this->_friends[$friend['name']]);
			}
		}

		ksort($this->_friends);
		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function updateScores()
	{
		$friendList = array_keys($this->_friends);
		$friendScores = $this->snapchat->getFriendScores($friendList);

		foreach ($friendList as $friend) {
			if (array_key_exists($friend, $friendScores))
				$this->_friends[$friend]["score"] = $friendScores[$friend];

			ksort($this->_friends[$friend]);
		}

		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function updateActiveFriends()
	{
		$activeFriends = $this->getActiveFriends();

		foreach ($this->_friends as $friend) {
			$this->_friends[$friend['name']]["active"] = in_array($friend['name'], $activeFriends);
			ksort($this->_friends[$friend['name']]);
		}

		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function addFriend($friend, $time = 0, $display = null, $age = null, $location = null)
	{
		$data = [
			"name" => $friend,
			"added_timestamp" => (($time == 0) ? time() : $time),
		];

		if (!is_null($display))
			$data["display"] = $display;

		if (!is_null($age))
			$data["age"] = $age;

		if (!is_null($location))
			$data["location"] = $location;

		$this->_friends[$friend] = $data;
		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function removeFriend($friend)
	{
		if ($this->isFriend($friend))
		{
			unset($this->_friends[$friend]);
			$this->saveFile($this->_friends, self::DATA_FILE);
		}
	}

	public function isFriend($friend)
	{
		return array_key_exists($friend, $this->_friends);
	}

	public function set($friend, $data, $value)
	{
		if (is_array($data))
		{
			$this->_friends[$friend] = $data;
			$this->saveFile($this->_friends, self::DATA_FILE);
		}
		else
		{
			$this->_friends[$friend][$data] = $value;
			ksort($this->_friends[$friend]);
			$this->saveFile($this->_friends, self::DATA_FILE);
		}
	}

	public function get($friend, $key = NULL)
	{
		if ($this->isFriend($friend))
		{
			if (is_null($key))
			{
				return $this->_friends[$friend];
			}
			elseif (array_key_exists($key, $this->_friends[$friend]))
			{
				return $this->_friends[$friend][$key];
			}
			else
			{
				return NULL;
			}
		}
		else
		{
			return NULL;
		}
	}

	public function getActiveFriends()
	{
		$activeFriends		= array();
		$dataFile 			= self::DATA_DIR . DIRECTORY_SEPARATOR . "active_friends.json";

		if (is_file($dataFile))
			$activeFriends = json_decode(file_get_contents($dataFile), true);

		$conversations 		= $this->snapchat->getConversations();
		$friendStories 		= $this->snapchat->getFriendStories();
		$myStories 			= $this->snapchat->getMyStories();

		foreach ($conversations as $conversation) {
			foreach ($conversation->participants as $participant) {
				if (!in_array($participant, $activeFriends)) {
					$activeFriends[] = $participant;
				}
			}
		}

		foreach ($friendStories as $story) {
			if (!in_array($story->username, $activeFriends)) {
				$activeFriends[] = $story->username;
			}
		}

		foreach ($myStories as $story) {
			foreach ($story->story_notes as $notes) {
				if (!in_array($notes->viewer, $activeFriends)) {
					$activeFriends[] = $notes->viewer;
				}
			}
		}

		sort($activeFriends);
		$this->saveFile($activeFriends, $dataFile);
		return $activeFriends;
	}

	private function saveFile($data, $filename)
	{
		$this->saveJSON($data, $filename);
	}

	public function saveJSON($data, $filename)
	{
		file_put_contents($filename, JSON::prettify($data));
	}
}
