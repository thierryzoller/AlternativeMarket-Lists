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
 * Essaie de trouver des aperçu dans les dépôts.
 *
 * @return array URLs des screenshots.
 */
function getScreenshots($gitId, $repository, $pluginId, $gitHubToken) {
    $patterns = array(
        $pluginId.'-Screenshot',
        $pluginId.'-screenshot',
        'Screenshot',
        'screenshot',
        'widget',
        'panel'
    );

    $basePath = array(
        '/docs/images',
        '/docs',
        '/images',
        ''
    );
    $result = [];
    foreach ($basePath as $imagePath) {
        $testUrl = 'https://api.github.com/repos/'.$gitId.'/'.$repository.'/contents'.$imagePath;
        $directoryContentRaw = downloadContent($testUrl, $gitHubToken);
        $directoryContent = json_decode($directoryContentRaw, true);
        if ($directoryContent !== null) {
            foreach ($patterns as $pattern) {
                foreach ($directoryContent as $file) {
                  if (isset($file['name'])) {
                    if ($file['name'] == $pattern.'.png') {
                      array_push($result, 'https://github.com/'.$gitId.'/'.$repository.'/raw/master'.$imagePath.'/'.$pattern.'.png');
                    }
                    for ($i = 0; $i < 11; ++$i) {
                      if ($file['name'] == $pattern.$i.'.png') {
                        array_push($result, 'https://github.com/'.$gitId.'/'.$repository.'/raw/master'.$imagePath.'/'.$pattern.$i.'.png');
                      }
                    }
                  }
                }
            }
        }
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
function scan($gitHubToken, $src, $dest, $withScreenshots) {
  $baseList = getBaseList($src);
  $errorsList = [];

  if ($baseList !== false) {
    if (DEBUG) {
      \var_dump($baseList);
    }

    $plugins = [];

    // Parcours de la liste
    foreach ($baseList as $fullName) {
      echo "$fullName\n";
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
          $plugin['gitId'] = $gitHubData['owner']['login'];
          $plugin['repository'] = $gitHubData['name'];
          $plugin['description'] = $gitHubData['description'];
          // Informations du plugin
          $plugin['id'] = $infoJsonData['id'];
          $plugin['name'] = $infoJsonData['name'];
          $plugin['licence'] = $infoJsonData['licence'];
          if (\array_key_exists('description', $infoJsonData) && $infoJsonData['description'] !== '') {
            $plugin['description'] = $infoJsonData['description'];
          }
          $plugin['require'] = '';
          if (\array_key_exists('require', $infoJsonData)) {
            $plugin['require'] = $infoJsonData['require'];
          }
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
            $masterBranchFound = false;
            foreach ($branchesData as $branch) {
              $branchData = [];
              if ($branch['name'] == 'master') {
                $masterBranchFound = true;
              }
              $branchData['name'] = $branch['name'];
              $branchData['hash'] = $branch['commit']['sha'];
              \array_push($plugin['branches'], $branchData);
            }
            if ($plugin['defaultBranch'] == 'develop' || $plugin['defaultBranch'] == 'beta') {
              if ($masterBranchFound === true) {
                $plugin['defaultBranch'] = 'master';
              }
            }
          }
          if ($withScreenshots) {
            $plugin['screenshots'] = getScreenshots($plugin['gitId'], $plugin['repository'], $plugin['id'], $gitHubToken);
          }
          \array_push($plugins, $plugin);
        }
        else {
          \array_push($errorsList, $fullName);
        }
      }
    }

    $needUpdate = false;
    // Comparaison avec l'ancien contenu
    if (\file_exists($dest)) {
      $oldContent = \file_get_contents($dest);
      $oldResult = \json_decode($oldContent, true);
      $oldPlugins = $oldResult['plugins'];

      if (json_encode($oldPlugins, true) !== json_encode($plugins, true)) {
        $needUpdate = true;
      }
    }
    else {
      $needUpdate = true;
    }

    // Met à jour le fichier que si nécessaire
    if ($needUpdate) {
      // Stockage des données
      $result = [];
      $result['version'] = \time();
      $result['plugins'] = $plugins;
      \file_put_contents($dest, json_encode($result, true));
    }
  }
  return $errorsList;
}

// Lecture du token GitHub
$gitHubToken = '';
if (\file_exists('.github-token')) {
  $gitHubToken = \file_get_contents('.github-token');
  $gitHubToken = \str_replace("\n", "", $gitHubToken);
}

// Récupération de la liste des sources
$listContent = \file_get_contents('lists.json');
$sourcesList = \json_decode($listContent, true);

$errorsList = [];
// Parcours des sources
foreach ($sourcesList as $source) {
  echo "Source : $source\n";
  $errors = scan($gitHubToken, 'lists/'.$source.'.json', 'results/'.$source.'.json', true);
  $errorsList = array_merge($errorsList, $errors);
}

if (count($errorsList) > 0) {
  echo "\nRécapitulatif des erreurs : \n";
  foreach ($errorsList as $repoError) {
    echo $repoError."\n";
  }
}
