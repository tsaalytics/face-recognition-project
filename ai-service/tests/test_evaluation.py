"""Synthetic fixtures test evaluator math only, not recognition accuracy."""
import math
import pytest
from evaluation import decision, latency, rate, summarize, validate_manifest


def test_open_set_denominators_and_misidentification():
    probes = [
        {'expected': 'a', 'scores': {'a': .9, 'b': .2}, 'latency_ms': 10},
        {'expected': 'a', 'scores': {}, 'latency_ms': 20, 'error': 'no_face_detected'},
        {'expected': 'a', 'scores': {'b': .95, 'a': .2}, 'latency_ms': 30},
        {'expected': None, 'scores': {'b': .95}, 'latency_ms': 40},
        {'expected': None, 'scores': {'a': .1}, 'latency_ms': 50},
    ]
    report = summarize(probes, .8)
    assert report['accuracy']['rate'] == 2 / 5
    assert report['far']['rate'] == 1 / 2
    assert report['frr']['rate'] == 1 / 3
    assert report['known_misidentification']['rate'] == 1 / 3
    assert report['extraction_failures'] == {'no_face_detected': 1}
    assert report['extraction_wall_latency']['p95_ms_nearest_rank'] == 50


def test_fail_closed_ties_invalid_values_and_absent_denominators():
    assert decision({'a': .9, 'b': .9}, .8) is None
    assert decision({'a': .9, 'b': .88}, .8) is None
    assert decision({'a': .8, 'b': .7}, .8) == 'a'
    assert decision({}, .8) is None
    for threshold in [0, -1, 1.1, math.nan, math.inf]:
        with pytest.raises(ValueError):
            decision({}, threshold)
    with pytest.raises(ValueError):
        decision({'a': math.nan}, .8)
    assert rate(0, 0)['rate'] is None
    assert rate(0, 10)['wilson_95'][1] > 0
    assert latency([])['mean_ms'] is None
    with pytest.raises(ValueError):
        latency([-1])


def manifest(tmp_path):
    records = []
    for split in ['calibration', 'test']:
        for subject in ['a', 'b']:
            for index in range(4):
                records.append({'split': split, 'subject': split + subject, 'role': 'gallery' if index < 3 else 'probe',
                                'session': 'enroll' if index < 3 else 'later', 'lighting': 'normal', 'path': str(len(records)) + '.png'})
        records.append({'split': split, 'subject': split + 'unknown', 'role': 'probe', 'session': 'later', 'lighting': 'dim', 'path': str(len(records)) + '.png'})
    for record in records:
        (tmp_path / record['path']).write_bytes(record['path'].encode())
    return {'schema_version': 1, 'consent_confirmed': True, 'records': records}


def test_manifest_requires_independent_cohorts_sessions_and_unique_images(tmp_path):
    data = manifest(tmp_path)
    assert len(validate_manifest(data, tmp_path)) == 18
    data['records'][3]['session'] = 'enroll'
    with pytest.raises(ValueError, match='separate capture'):
        validate_manifest(data, tmp_path)
    data['records'][3]['session'] = 'later'
    data['records'][-1]['subject'] = 'calibrationunknown'
    with pytest.raises(ValueError, match='overlap'):
        validate_manifest(data, tmp_path)
    data['records'][-1]['subject'] = 'testunknown'
    data['records'][-1]['path'] = data['records'][0]['path']
    with pytest.raises(ValueError, match='Duplicate'):
        validate_manifest(data, tmp_path)


def test_manifest_rejects_missing_consent_traversal_and_missing_unknown(tmp_path):
    data = manifest(tmp_path)
    data['consent_confirmed'] = False
    with pytest.raises(ValueError):
        validate_manifest(data, tmp_path)
    data['consent_confirmed'] = True
    data['records'][0]['path'] = '../escape.png'
    with pytest.raises(ValueError):
        validate_manifest(data, tmp_path)
    data = manifest(tmp_path)
    data['records'] = [r for r in data['records'] if 'unknown' not in r['subject']]
    with pytest.raises(ValueError, match='unknown'):
        validate_manifest(data, tmp_path)
