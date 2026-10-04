"""Action replay exercises the real worker, persistence and HTTP contract offline."""

import json
from datetime import datetime

import httpx
from baselhack.batch_assessment import analyze_batch
from baselhack.batch_demo import prepare_action_demo
from baselhack.data_engine.database import Database
from baselhack.integration import create_app
from baselhack.interfaces import OperationalDataset
from baselhack.storage import ROOT
from fastapi.testclient import TestClient


def test_action_demo_preserves_acceptance_cases_and_serves_computed_actions(
    tmp_path, monkeypatch
):
    def forbidden(*args, **kwargs):
        raise AssertionError("Demo replay and batch worker must stay offline")

    monkeypatch.setattr(httpx.HTTPTransport, "handle_request", forbidden)
    from baselhack.ingestion import rhine, traffic, weather

    for module in (rhine, traffic, weather):
        monkeypatch.setattr(module, "download", forbidden)

    database = Database(tmp_path / "action-demo.sqlite3")
    database.initialize()
    acceptance = OperationalDataset.model_validate_json(
        (ROOT / "output/operations-demo.json").read_text()
    )
    database.store_operations(acceptance)
    dataset, records = prepare_action_demo(database)
    expected = json.loads((ROOT / "output/batch-assessments-demo.json").read_text())
    for reference in expected:
        record = analyze_batch(
            database,
            acceptance.dataset_id,
            reference["batch_id"],
            datetime.fromisoformat(reference["as_of"]),
        )
        assert record.model_dump(mode="json") == reference

    assert [record.recommendation.action for record in records] == [
        "BUFFER",
        "EXPEDITE",
    ]
    assert dataset.dataset_id != acceptance.dataset_id
    client = TestClient(create_app(database))
    for record in records:
        assert record.as_of < record.production_readiness.planned_charge_at
        assert record.recommendation.qa_release_authorized is False
        rerun = analyze_batch(
            database, dataset.dataset_id, record.batch_id, record.as_of
        )
        assert rerun == record
        response = client.get(
            f"/api/integration/batches/{record.batch_id}/assessments/latest",
            params={"dataset_id": dataset.dataset_id},
        )
        assert response.status_code == 200
        assert response.json() == record.model_dump(mode="json")
    incoming = records[1].incoming_shipments[0]
    assert incoming.status == "MODELED"
    assert incoming.logistics.features.weather.observed_at == dataset.reference_at
    assert records[1].production_readiness.released_reserved_quantity_kg == 0


def test_action_demo_writes_snapshots_to_the_chosen_folder_only(tmp_path):
    from baselhack.batch_demo import main

    committed = {
        name: (ROOT / "output" / name).read_bytes()
        for name in (
            "operations-action-demo.json",
            "batch-action-assessments-demo.json",
        )
    }

    assert (
        main(
            [
                "--database",
                str(tmp_path / "action-demo.sqlite3"),
                "--output-dir",
                str(tmp_path / "out"),
            ]
        )
        == 0
    )

    for name, content in committed.items():
        assert (tmp_path / "out" / name).is_file()
        assert (ROOT / "output" / name).read_bytes() == content
