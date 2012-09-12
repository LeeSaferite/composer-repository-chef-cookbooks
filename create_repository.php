<?php

function prepare_version($ref) {

    if (strpos($ref, 'refs/heads') === false && strpos($ref, 'refs/tags') === false) {
        return null;
    }

    $refName = str_replace('refs/heads/', '', str_replace('refs/tags/', '', $ref));

    if (preg_match('/\d+\.\d+\.\d+\w{0,20}/', $refName)) {
        return $refName;
    }

    return 'dev-' . $refName;
}

function prepare_ref($ref) {
    return str_replace('refs/heads/', '', str_replace('refs/tags/', '', $ref));
}

function fetch_all($url) {

    $data = array();
    $page = 1;

    while (
        ($contents = file_get_contents("{$url}?page={$page}")) &&
        ($decoded = json_decode($contents)) &&
        count($decoded) > 0
    ) {
        $page++;

        $data = array_merge(
            $data,
            $decoded
        );
    }

    return $data;
}

$packages = array();

$repositories = fetch_all('https://api.github.com/orgs/opscode-cookbooks/repos');

foreach ($repositories as $repository) {

    $refs = json_decode(file_get_contents("https://api.github.com/repos/{$repository->full_name}/git/refs"));

    $versions = array();

    foreach ($refs as $ref) {

        $version = prepare_version($ref->ref);

        if (!$version) {
            continue;
        }

        $versions[$version] = array(
            'name' => $repository->full_name,
            'type' => 'chef-cookbook',
            'version' => $version,
            'source' => array(
                'type' => 'git',
                'url' => $repository->git_url,
                'reference' => prepare_ref($ref->ref)
            ),
            'require' => array(
                'dancras/chef-cookbook-installer' => '*'
            )
        );
    }

    $packages[$repository->full_name] = $versions;
}

$output = array('packages' => $packages);

echo str_replace('\\/', '/', json_encode($output));
