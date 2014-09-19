<?php
//This script will update all records in the movieinfo table where there is no cover
require_once(dirname(__FILE__) . "/../../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(NN_TMUX . 'lib/Film.php');



$pdo = new DB();

$movie = new \Film(array('Echo' => true, 'Settings' => $pdo));

$movies = $pdo->queryDirect("SELECT imdbID FROM movieinfo WHERE cover = 0 ORDER BY year ASC, ID DESC");
if ($movies instanceof \Traversable) {
	echo $pdo->log->primary("Updating " . number_format($movies->rowCount()) . " movie covers.");
	foreach ($movies as $mov) {
		$starttime = microtime(true);
		$mov = $movie->updateMovieInfo($mov['imdbID']);

		// tmdb limits are 30 per 10 sec, not certain for imdb
		$diff = floor((microtime(true) - $starttime) * 1000000);
		if (333333 - $diff > 0) {
			echo "\nsleeping\n";
			usleep(333333 - $diff);
		}
	}
	echo "\n";
}