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
    if (DEBUG) {
      \var_dump($url);
    }
    $content = false;
    $curlSession = \curl_init();
    if ($curlSession !== false) {
        \curl_setopt($curlSession, CURLOPT_URL, $url);
        \curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlSession, CURLOPT_USERAGENT, 'AlternativeMarketUpdate');
        $content = \curl_exec($curlSession);
        if (DEBUG) {
          \var_dump($content);
        }
        \curl_close($curlSession);
    }
    return $content;
}

/**
 * Lire la liste des dépôts
 */
function getBaseList() {
  $result = false;
  if (\file_exists('base-list.json')) {
    $baseContent = \file_get_contents('base-list.json');
    $result = \json_decode($baseContent, true);
  }
  return $result;
}

// Lecture du token GitHub
$gitHubToken = '';
if (\file_exists('.github-token')) {
  $gitHubToken = \file_get_contents('.github-token');
  $gitHubToken = \str_replace("\n", "", $gitHubToken);
}

$baseList = getBaseList();

if (DEBUG) {
  \var_dump($baseList);
}

$plugins = [];

// Parcours de la liste
foreach ($baseList as $fullName) {
  $gitHubContent = downloadContent('https://api.github.com/repos/'.$fullName, $gitHubToken);
  $infoJsonContent = downloadContent('https://raw.githubusercontent.com/'.$fullName.'/master/plugin_info/info.json');
  if (DEBUG) {
    \var_dump($gitHubContent);
    \var_dump($infoJsonContent);
  }
  // Test si le dépôt fonctionne et que le fichier définissant les informations du plugin existe.
  if ($gitHubContent !== false && $infoJsonContent !== false) {
    $gitHubData = \json_decode($gitHubContent, true);
    $infoJsonData = \json_decode($infoJsonContent, true);
    // Test si les données du plugin sont valides
    if (\is_array($infoJsonData)) {
      $plugin = [];
      // Informations de GitHub
      $plugin['defaultBranch'] = $gitHubData['default_branch'];
      $plugin['repository'] = $gitHubData['name'];
      $plugin['gitId'] = $gitHubData['owner']['login'];
      // Informations du plugin
      $plugin['id'] = $infoJsonData['id'];
      $plugin['name'] = $infoJsonData['name'];
      $plugin['licence'] = $infoJsonData['licence'];
      $plugin['description'] = '';
      if (\array_key_exists('description', $infoJsonData)) {
        $plugin['description'] = $infoJsonData['description'];
      }
      $plugin['require'] = $infoJsonData['require'];
      $plugin['category'] = $infoJsonData['category'];
      $plugin['documentation'] = [];
      if (\array_key_exists('documentation', $infoJsonData)) {
        $plugin['documentation'] = $infoJsonData['documentation'];
      }
      $plugin['changelog'] = [];
      if (\array_key_exists('changelog', $infoJsonData)) {
        $plugin['changelog'] = $infoJsonData['changelog'];
      }
      $plugin['author'] = $infoJsonData['author'];
      // Informations des branches
      $plugin['branches'] = [];
      $branchesContent = downloadContent('https://api.github.com/repos/'. $fullName .'/branches', $gitHubToken);
      if ($branchesContent) {
        $branchesData = \json_decode($branchesContent, true);
        foreach ($branchesData as $branch) {
          $branchData = [];
          $branchData['name'] = $branch['name'];
          $branchData['hash'] = $branch['commit']['sha'];
          \array_push($plugin['branches'], $branchData);
        }
      }
      \array_push($plugins, $plugin);
      echo "OK $fullName\n";
    }
    else {
      echo "ERROR $fullName\n";
    }
  }
}

$needUpdate = false;
// Comparaison avec l'ancien contenu
if (\file_exists('result.json')) {
  $oldContent = \file_get_contents('result.json');
  $oldResult = \json_decode($oldContent, true);
  $oldPlugins = $oldResult['plugins'];

  if (json_encode($oldPlugins, true) !== json_encode($plugins, true)) {
    $needUpdate = true;
  }
}

// Met à jour le fichier que si nécessaire
if ($needUpdate) {
  // Stockage des données
  $result = [];
  $result['version'] = \time();
  $result['plugins'] = $plugins;
  \file_put_contents('result.json', json_encode($result, true));
}
