from subprocess import run, DEVNULL, PIPE


SKELETON_XML_URL = (
    "https://raw.githubusercontent.com/cloud-py-api/nc_py_api/main/examples/as_app/skeleton/appinfo/info.xml"
)


def register_daemon(nextcloud_url: str):
    run(
        "php occ app_api:daemon:register docker_local_sock "
        f"Docker docker-install http /var/run/docker.sock {nextcloud_url}".split(),
        stderr=DEVNULL,
        stdout=DEVNULL,
        check=True,
    )


def unregister_daemon():
    run(
        "php occ app_api:daemon:unregister docker_local_sock".split(),
        stderr=DEVNULL,
        stdout=DEVNULL,
        check=True,
    )


def deploy_register():
    run(
        f"php occ app_api:app:deploy skeleton docker_local_sock --info-xml {SKELETON_XML_URL}".split(),
        stderr=DEVNULL,
        stdout=DEVNULL,
        check=True,
    )
    run(
        f"php occ app_api:app:register skeleton docker_local_sock --info-xml {SKELETON_XML_URL}".split(),
        stderr=DEVNULL,
        stdout=DEVNULL,
        check=True,
    )


if __name__ == "__main__":
    # nextcloud_url should be without slash at the end
    register_daemon("http://127.0.0.1:8080/")
    r = run("php occ app_api:daemon:list".split(), stdout=PIPE, stderr=PIPE, check=True)
    assert not r.stderr.decode("UTF-8")
    r_output = r.stdout.decode("UTF-8")
    assert r_output.find("http://127.0.0.1:8080/") == -1
    assert r_output.find("http://127.0.0.1:8080") != -1
    unregister_daemon()
    # nextcloud_url should be without slash at the end but with "index.php"
    register_daemon("http://127.0.0.1:8080/index.php/")
    r = run("php occ app_api:daemon:list".split(), stdout=PIPE, stderr=PIPE, check=True)
    assert not r.stderr.decode("UTF-8")
    r_output = r.stdout.decode("UTF-8")
    assert r_output.find("http://127.0.0.1:8080/index.php/") == -1
    assert r_output.find("http://127.0.0.1:8080/index.php") != -1
    # silent should not fail, as there are not such ExApp
    r = run("php occ app_api:app:unregister skeleton --silent".split(), stdout=PIPE, stderr=PIPE, check=True)
    assert not r.stderr.decode("UTF-8")
    r_output = r.stdout.decode("UTF-8")
    assert not r_output, f"Output should be empty: {r_output}"
    # without "--silent" it should fail, as there are not such ExApp
    r = run("php occ app_api:app:unregister skeleton".split(), stdout=PIPE)
    assert r.returncode
    assert r.stdout.decode("UTF-8"), "Output should be non empty"
    # testing if "--keep-data" works.
    deploy_register()
    r = run("php occ app_api:app:unregister skeleton --keep-data".split(), stdout=PIPE, check=True)
    assert r.stdout.decode("UTF-8"), "Output should be non empty"
    run("docker volume inspect nc_app_skeleton_data".split(), check=True)
    # test if volume will be removed without "--keep-data"
    deploy_register()
    run("php occ app_api:app:unregister skeleton".split(), check=True)
    r = run("docker volume inspect nc_app_skeleton_data".split())
    assert r.returncode
    # test "--force" option
    deploy_register()
    run("docker container rm --force nc_app_skeleton".split(), check=True)
    r = run("php occ app_api:app:unregister skeleton".split())
    assert r.returncode
    r = run("php occ app_api:app:unregister skeleton --silent".split())
    assert r.returncode
    run("php occ app_api:app:unregister skeleton --force".split(), check=True)
