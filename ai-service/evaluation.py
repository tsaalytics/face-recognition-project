"""Offline open-set evaluation. Outputs aggregates only, never vectors or identities."""
import hashlib
import math
from collections import Counter
from pathlib import Path
import statistics


def decision(scores, threshold, margin=0.05):
    if not math.isfinite(threshold) or not 0 < threshold <= 1:
        raise ValueError('Invalid threshold')
    if not math.isfinite(margin) or not 0 < margin <= 1:
        raise ValueError('Invalid margin')
    ranked = sorted(scores.items(), key=lambda item: item[1], reverse=True)
    if any(not math.isfinite(score) or not -1 <= score <= 1 for _, score in ranked):
        raise ValueError('Invalid score')
    if not ranked or ranked[0][1] < threshold:
        return None
    if len(ranked) > 1 and ranked[0][1] - ranked[1][1] < margin:
        return None
    return ranked[0][0]


def rate(count, total):
    """Wilson 95% interval; repeated probes are correlated, not independent people."""
    if not total:
        return {'count': count, 'total': total, 'rate': None, 'wilson_95': None}
    p, z = count / total, 1.959963984540054
    denominator = 1 + z * z / total
    center = (p + z * z / (2 * total)) / denominator
    radius = z * math.sqrt(p * (1 - p) / total + z * z / (4 * total * total)) / denominator
    return {'count': count, 'total': total, 'rate': p,
            'wilson_95': [max(0, center - radius), min(1, center + radius)]}


def latency(values):
    if any(not math.isfinite(value) or value < 0 for value in values):
        raise ValueError('Invalid timing')
    ordered = sorted(values)
    return {'count': len(values), 'mean_ms': statistics.mean(values) if values else None,
            'p95_ms_nearest_rank': ordered[math.ceil(.95 * len(values)) - 1] if values else None}


def summarize(probes, threshold, margin=0.05):
    counts = Counter()
    errors = Counter()
    for probe in probes:
        known = probe['expected'] is not None
        predicted = decision(probe['scores'], threshold, margin)
        counts['known' if known else 'unknown'] += 1
        counts['correct'] += predicted == probe['expected']
        counts['false_accept'] += not known and predicted is not None
        counts['false_reject'] += known and predicted is None
        counts['misidentified'] += known and predicted is not None and predicted != probe['expected']
        if probe.get('error'):
            errors[probe['error']] += 1
    return {'probe_count': len(probes), 'known_count': counts['known'], 'unknown_count': counts['unknown'],
            'accuracy': rate(counts['correct'], len(probes)),
            'far': rate(counts['false_accept'], counts['unknown']),
            'frr': rate(counts['false_reject'], counts['known']),
            'known_misidentification': rate(counts['misidentified'], counts['known']),
            'extraction_failures': dict(errors),
            'extraction_wall_latency': latency([probe['latency_ms'] for probe in probes]),
            'successful_extraction_wall_latency': latency([probe['latency_ms'] for probe in probes if not probe.get('error')])}


def validate_manifest(data, root: Path):
    if data.get('schema_version') != 1 or data.get('consent_confirmed') is not True:
        raise ValueError('Manifest version or consent invalid')
    records = data.get('records')
    if not isinstance(records, list) or not records:
        raise ValueError('Empty manifest')
    subjects = {'calibration': set(), 'test': set()}
    gallery = {'calibration': {}, 'test': {}}
    probes = {'calibration': [], 'test': []}
    hashes = set()
    result = []
    root = root.resolve()
    for record in records:
        split, role = record.get('split'), record.get('role')
        if split not in subjects or role not in {'gallery', 'probe'}:
            raise ValueError('Invalid split or role')
        for field in ('subject', 'session', 'path'):
            if not isinstance(record.get(field), str) or not record[field].strip():
                raise ValueError('Missing manifest field')
        if record.get('lighting') not in {'bright', 'normal', 'dim', 'backlit'}:
            raise ValueError('Invalid lighting category')
        path = (root / record['path']).resolve()
        if Path(record['path']).is_absolute() or not path.is_relative_to(root) or not path.is_file():
            raise ValueError('Image must be inside dataset root')
        if path.stat().st_size > 1024 * 1024:
            raise ValueError('Image exceeds Laravel one MiB limit')
        digest = hashlib.sha256(path.read_bytes()).digest()
        if digest in hashes:
            raise ValueError('Duplicate image bytes across dataset')
        hashes.add(digest)
        subjects[split].add(record['subject'])
        if role == 'gallery':
            gallery[split].setdefault(record['subject'], []).append(record)
        else:
            probes[split].append(record)
        result.append({**record, '_path': path})
    if subjects['calibration'] & subjects['test']:
        raise ValueError('Participant overlap between calibration and test')
    for split in subjects:
        if len(gallery[split]) < 2 or not probes[split]:
            raise ValueError('Each split requires two gallery subjects and probes')
        for subject, samples in gallery[split].items():
            if not 3 <= len(samples) <= 5:
                raise ValueError('Each gallery subject needs three to five samples')
            sessions = {sample['session'] for sample in samples}
            matching = [probe for probe in probes[split] if probe['subject'] == subject]
            if not matching or any(probe['session'] in sessions for probe in matching):
                raise ValueError('Known probes need separate capture sessions')
        if not any(probe['subject'] not in gallery[split] for probe in probes[split]):
            raise ValueError('Each split needs unknown participants')
    return result
