<?php

/* This file is part of NextDom.
 *
 * NextDom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NextDom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextDom. If not, see <http://www.gnu.org/licenses/>.
 */

\define('DEBUG', false);

// Télécharger un document
/**
 * Télécharger un document
 *
 * @param string $url Lien du document à télécharger.
 * @param string $gitHubToken Token GitHub pour éviter les limitations
 *
 * @return string Contenu du fichier.
 */
function downloadContent($url, $gitHubToken = '')
{
    if ($gitHubToken !== '') {
        $toAdd = 'access_token=' . $gitHubToken;
        // Test si un paramètre a déjà été passé
        if (\strpos($url, '?') !== false) {
            $url = $url . '&' . $toAdd;
        } else {
            $url = $url . '?' . $toAdd;
        }
    }
    $url .= '&per_page=100';
    if (DEBUG) {
      \var_dump($url);
    }
    $content = false;
    $curlSession = \curl_init();
    if ($curlSession !== false) {
        \curl_setopt($curlSession, CURLOPT_URL, $url);
        \curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlSession, CURLOPT_USERAGENT, 'LSHMarketUpdate');
        $content = \curl_exec($curlSession);
        if (DEBUG) {
          \var_dump($content);
        }
        \curl_close($curlSession);
    }
    return $content;
}

/**
 * Test si l'URL pointe vers un document téléchargeable.
 */
function isDownloadable($url) {
    $result = false;
    $curlSession = curl_init();
    curl_setopt($curlSession, CURLOPT_URL,$url);
    curl_setopt($curlSession, CURLOPT_NOBODY, 1);
    curl_setopt($curlSession, CURLOPT_FAILONERROR, 1);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
    if(curl_exec($curlSession) !== false) {
        $result = true;
    }
    return $result;
}

/**
 * Lire la liste des dépôts
 */
function getBaseList($filename) {
  $result = false;
  if (\file_exists($filename)) {
    $content = \file_get_contents($filename);
    $result = \json_decode($content, true);
  }
  return $result;
}

/**
 * Scan une liste de dépots GitHub
 */
function scan($gitHubToken, $githubs, $baseList) {
    $list = $baseList;
    foreach ($githubs as $github) {
        echo "=== $github ===\n";
        $content = downloadContent('https://api.github.com/orgs/'.$github.'/repos', $gitHubToken);
        if (strstr($content, '"message":"Not Found"') !== false) {
            $content = downloadContent('https://api.github.com/users/'.$github.'/repos', $gitHubToken);
        }
        try {
            $repos = json_decode($content, true);
            foreach ($repos as $repo) {
                $isPlugin = isDownloadable('https://raw.githubusercontent.com/'.$repo['full_name'].'/master/plugin_info/info.json');
                $arraySearchResult = array_search($repo['full_name'], $list);
                if ($arraySearchResult !== false) {
                    if (!$isPlugin) {
                        echo "Erreur : ".$repo['full_name']."\n";
                    }
                    array_splice($list, $arraySearchResult, 1);
                }
                else {
                    if ($isPlugin) {
                        echo "A rajouter : ".$repo['full_name']."\n";
                    }
                }
            }
        }
        catch (Exception $e) {

        }
    }
    if (count($list) > 0) {
        echo "=== Absents ===\n";
        foreach ($list as $item) {
            echo $item."\n";
        }
    }
}

// Lecture du token GitHub
$gitHubToken = '';
if (\file_exists('.github-token')) {
  $gitHubToken = \file_get_contents('.github-token');
  $gitHubToken = \str_replace("\n", "", $gitHubToken);
}

$reportContent = \file_get_contents('report-list.json');
$reportList = \json_decode($reportContent, true);

// Récupération de la liste des sources
$listContent = \file_get_contents('lists.json');
$sourcesList = \json_decode($listContent, true);

$reposList = [];
// Parcours des sources
foreach ($sourcesList as $source) {
    $list = getBaseList('lists/'.$source.'.json');
    $reposList = array_merge($reposList, $list);
}

scan($gitHubToken, $reportList, $reposList);
