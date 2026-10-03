"""Separate forward action replay using generated operations and unchanged real cache."""

import argparse
import json
from pathlib import Path

from baselhack.batch_assessment import analyze_batch
from baselhack.data_engine.database import Database
from baselhack.data_engine.ingest import import_observations
from baselhack.data_engine.operations import generate
from baselhack.interfaces import BatchAnalysisProfile, RealObservations
from baselhack.storage import ROOT, read_yaml


def prepare_action_demo(database):
    """Persist a distinct dataset; let the existing worker compute every action."""
    settings = {
        **read_yaml("config/operations_demo.yaml"),
        **read_yaml("config/batch_action_demo.yaml"),
    }
    dataset = generate(settings)
    profile_settings = read_yaml("config/batch_assessment.yaml")
    logistics_config = profile_settings.pop("logistics_config")
    profile = BatchAnalysisProfile.model_validate(
        {
            **profile_settings,
            "logistics": read_yaml(f"config/{logistics_config}"),
        }
    )
    observations = RealObservations.model_validate_json(
        (ROOT / "data/cache/logistics_basel.json").read_text()
    )
    database.initialize()
    import_observations(database, observations)
    database.store_batch_profile(profile)
    database.store_operations(dataset)
    records = [
        analyze_batch(
            database,
            dataset.dataset_id,
            batch.id,
            dataset.reference_at,
            profile_id=profile.profile_id,
        )
        for batch in dataset.batches
    ]
    return dataset, records


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--database", type=Path, default=ROOT / ".runtime/batch-action-demo.sqlite3"
    )
    args = parser.parse_args(argv)
    dataset, records = prepare_action_demo(Database(args.database))
    (ROOT / "output/operations-action-demo.json").write_text(
        dataset.model_dump_json(indent=2) + "\n"
    )
    (ROOT / "output/batch-action-assessments-demo.json").write_text(
        json.dumps([record.model_dump(mode="json") for record in records], indent=2)
        + "\n"
    )
    for record in records:
        print(f"{record.batch_id}: {record.recommendation.action}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
