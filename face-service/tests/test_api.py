import pytest
from fastapi.testclient import TestClient

from app.config import Settings
from app.main import create_app

from .conftest import FakeEngine, upload


def seed(client, event="evt1"):
    upload(client, event, "p1", "alice,bob")
    upload(client, event, "p2", "alice")
    upload(client, event, "p3", "carol,bob")
    upload(client, event, "p4", "dave")


def test_health_is_public(settings, store):
    app = create_app(settings=settings, engine=FakeEngine(), store=store)
    assert TestClient(app).get("/health").json() == {"status": "ok"}


def test_requires_token(settings, store):
    app = create_app(settings=settings, engine=FakeEngine(), store=store)
    plain = TestClient(app)
    assert plain.get("/v1/events/e/stats").status_code == 401
    bad = TestClient(app, headers={"Authorization": "Bearer wrong"})
    assert bad.get("/v1/events/e/stats").status_code == 401


def test_refuses_to_start_without_token(tmp_path):
    with pytest.raises(RuntimeError):
        create_app(settings=Settings(api_token="", data_dir=tmp_path), engine=FakeEngine())


def test_index_returns_faces_with_ids(client):
    res = upload(client, "evt1", "p1", "alice,bob")
    assert res.status_code == 200
    faces = res.json()["faces"]
    assert len(faces) == 2
    assert all(len(f["face_id"]) == 32 for f in faces)


def test_reindex_replaces_faces(client):
    upload(client, "evt1", "p1", "alice,bob")
    upload(client, "evt1", "p1", "alice")
    assert client.get("/v1/events/evt1/stats").json()["faces"] == 1


def test_gallery_search_finds_all_photos_of_person(client):
    seed(client)
    faces = client.get("/v1/events/evt1/photos/p1/faces").json()["faces"]
    alice = faces[0]["face_id"]  # alice est à gauche (x=0)
    res = client.post("/v1/events/evt1/search/faces", json={"face_ids": [alice]}).json()
    assert {m["photo_id"] for m in res["matches"]} == {"p1", "p2"}
    assert all(m["tier"] == "strong" for m in res["matches"])


def test_search_never_leaves_the_event(client):
    seed(client, "evt1")
    upload(client, "evt2", "x1", "alice")
    alice = client.get("/v1/events/evt1/photos/p2/faces").json()["faces"][0]["face_id"]
    res = client.post("/v1/events/evt1/search/faces", json={"face_ids": [alice]}).json()
    assert "x1" not in {m["photo_id"] for m in res["matches"]}
    # Un visage d'un autre événement est inconnu ici.
    other = client.get("/v1/events/evt2/photos/x1/faces").json()["faces"][0]["face_id"]
    assert client.post("/v1/events/evt1/search/faces", json={"face_ids": [other]}).status_code == 404


def test_selfie_search_uses_largest_face_and_returns_query(client):
    seed(client)
    res = client.post("/v1/events/evt1/search/selfie",
                      files={"image": ("s.jpg", b"carol,bob", "image/jpeg")})
    assert res.status_code == 200
    body = res.json()
    # Le visage le plus grand est le second (w = 81) : bob.
    assert {m["photo_id"] for m in body["matches"]} == {"p1", "p3"}
    assert body["query_face"]["face_id"] == "selfie"


def test_selfie_without_face(client):
    seed(client)
    res = client.post("/v1/events/evt1/search/selfie", files={"image": ("s.jpg", b",", "image/jpeg")})
    assert res.status_code == 422


def test_invalid_image(client):
    res = upload(client, "evt1", "p9", "not-an-image")
    assert res.status_code == 422


def test_rejects_bad_ids(client):
    assert upload(client, "evt 1", "p1", "alice").status_code in (404, 422)
    assert upload(client, "evt1", "../p1", "alice").status_code in (404, 422)


def test_upload_size_limit(settings, store):
    settings.max_upload_mb = 0
    app = create_app(settings=settings, engine=FakeEngine(), store=store)
    c = TestClient(app, headers={"Authorization": "Bearer test-token"})
    assert upload(c, "evt1", "p1", "alice").status_code == 413


def test_delete_photo_then_event(client):
    seed(client)
    assert client.delete("/v1/events/evt1/photos/p1").json()["deleted_faces"] == 2
    alice = client.get("/v1/events/evt1/photos/p2/faces").json()["faces"][0]["face_id"]
    res = client.post("/v1/events/evt1/search/faces", json={"face_ids": [alice]}).json()
    assert {m["photo_id"] for m in res["matches"]} == {"p2"}
    assert client.delete("/v1/events/evt1").json()["deleted_faces"] == 4
    assert client.get("/v1/events/evt1/stats").json() == {"event_id": "evt1", "faces": 0, "photos_with_faces": 0}


def test_multi_face_query_and_limit(client):
    seed(client)
    a1 = client.get("/v1/events/evt1/photos/p1/faces").json()["faces"][0]["face_id"]
    a2 = client.get("/v1/events/evt1/photos/p2/faces").json()["faces"][0]["face_id"]
    res = client.post("/v1/events/evt1/search/faces", json={"face_ids": [a1, a2], "limit": 1}).json()
    assert len(res["matches"]) == 1
