"""Consenting local dataset only. No database access, uploads or biometric output."""
import argparse
from collections import Counter
from contextlib import redirect_stdout, redirect_stderr
from datetime import datetime, timezone
import importlib.metadata
from io import BytesIO, StringIO
import json
from pathlib import Path
import platform
import sys
from time import perf_counter

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from evaluation import summarize, validate_manifest


def run(args):
    # Heavy inference imports are deferred so --help requires no model load.
    from PIL import Image
    from config import Settings
    from face_engine import FaceEngine, FaceError
    from model_runtime import ModelRuntime
    from evaluation import latency
    import numpy as np

    data = json.loads(args.manifest.read_text(encoding='utf-8-sig'))
    records = validate_manifest(data, args.manifest.parent)
    selected = [record for record in records if record['split'] == args.split]
    settings = Settings()
    runtime = ModelRuntime()
    started = perf_counter()
    # Third-party startup diagnostics are suppressed; never include paths in reports.
    with redirect_stdout(StringIO()), redirect_stderr(StringIO()):
        runtime.load(settings)
    startup_ms = (perf_counter() - started) * 1000
    try:
        if not runtime.ready:
            raise ValueError('Model unavailable')
        engine = FaceEngine(settings, runtime)
        gallery = {}
        gallery_quality = []
        gallery_times = []
        sessions = set()
        for record in selected:
            if record['role'] != 'gallery':
                continue
            payload = record['_path'].read_bytes()
            with Image.open(BytesIO(payload)) as image:
                mime = Image.MIME.get(image.format)
            started = perf_counter()
            result = engine.extract(payload, mime)
            gallery_times.append((perf_counter() - started) * 1000)
            gallery.setdefault(record['subject'], []).append(result['embedding'])
            gallery_quality.append(result['quality'])
            sessions.add((record['subject'], record['session']))
        # Same enrollment consistency + near-duplicate rules as Laravel.
        for subject, vectors in gallery.items():
            if any(float(np.dot(left, right)) < .65 for i, left in enumerate(vectors) for right in vectors[i + 1:]):
                raise ValueError('Gallery enrollment inconsistent')
            unique = []
            for vector in vectors:
                if not any(float(np.dot(vector, other)) >= .9999 for other in unique):
                    unique.append(vector)
            gallery[subject] = unique
        probes, matching_times = [], []
        for record in selected:
            if record['role'] != 'probe':
                continue
            payload = record['_path'].read_bytes()
            with Image.open(BytesIO(payload)) as image:
                mime = Image.MIME.get(image.format)
            started = perf_counter()
            try:
                result = engine.extract(payload, mime)
                extraction_ms = (perf_counter() - started) * 1000
                match_start = perf_counter()
                scores = {subject: max(max(-1., min(1., float(np.dot(result['embedding'], vector)))) for vector in vectors) for subject, vectors in gallery.items()}
                matching_times.append((perf_counter() - match_start) * 1000)
                error = None
            except FaceError as exc:
                # Count acquisition/quality failures in denominators, not silent exclusions.
                extraction_ms = (perf_counter() - started) * 1000
                if exc.status >= 500:
                    raise ValueError('Inference failed') from None
                scores, error = {}, exc.code
            probes.append({'expected': record['subject'] if record['subject'] in gallery else None,
                           'scores': scores, 'error': error, 'latency_ms': extraction_ms,
                           'lighting': record['lighting']})
        thresholds = args.thresholds if args.split == 'calibration' else [args.threshold]
        return {'schema_version': 1, 'status': 'participant_evaluation', 'split': args.split,
                'timestamp_utc': datetime.now(timezone.utc).isoformat(),
                'model_identifier': runtime.identifier, 'gallery_subject_count': len(gallery),
                'gallery_embedding_count': sum(map(len, gallery.values())),
                'probe_subject_count': len({r['subject'] for r in selected if r['role'] == 'probe'}),
                'capture_session_count': len({(r['subject'], r['session']) for r in selected}),
                'lighting_counts': dict(Counter(p['lighting'] for p in probes)),
                'runtime': {'os': platform.system(), 'cpu': args.cpu, 'ram_gib': args.ram_gib,
                            'python': platform.python_version(), 'provider': 'CPUExecutionProvider',
                            'dependencies': {name: importlib.metadata.version(name) for name in ['numpy', 'onnxruntime', 'insightface', 'opencv-python']}},
                'quality_settings': {name: getattr(settings, name) for name in ['face_min_size', 'face_min_detection_score', 'face_min_blur_variance', 'face_min_brightness', 'face_max_brightness']},
                'enrollment_rules': {'consistency': .65, 'duplicate': .9999},
                'startup_with_synthetic_warmup_ms': startup_ms,
                'gallery_extraction_wall_latency': latency(gallery_times),
                'python_matching_wall_latency': latency(matching_times),
                'laravel_end_to_end_latency': None,
                'results': [{'threshold': t, 'margin': args.margin, **summarize(probes, t, args.margin),
                             'by_lighting': {light: summarize([p for p in probes if p['lighting'] == light], t, args.margin) for light in sorted({p['lighting'] for p in probes})}} for t in thresholds],
                'limitations': ['No liveness; offline Python matching excludes Laravel decryption, HTTP and transactions.',
                               'Wilson intervals assume independent probes; repeated probes per person are correlated.',
                               'Never select a threshold using test results; a revised threshold needs a new holdout cohort.',
                               'Enrollment consistency and duplicate thresholds must match Laravel configuration.']}
    finally:
        runtime.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('manifest', type=Path)
    parser.add_argument('--consent-confirmed', action='store_true', required=True)
    parser.add_argument('--split', choices=['calibration', 'test'], required=True)
    parser.add_argument('--thresholds', type=float, nargs='+', help='Calibration candidates only')
    parser.add_argument('--threshold', type=float, help='Frozen threshold for test only')
    parser.add_argument('--margin', type=float, default=.05)
    parser.add_argument('--cpu', required=True, help='Hardware model, without personal identifiers')
    parser.add_argument('--ram-gib', type=float, required=True)
    parser.add_argument('--output', type=Path, required=True, help='Private aggregate JSON; never overwritten')
    args = parser.parse_args()
    from evaluation import decision
    if args.split == 'test' and (args.threshold is None or args.thresholds is not None):
        parser.error('Test requires one frozen --threshold and no --thresholds')
    if args.split == 'calibration' and (not args.thresholds or args.threshold is not None):
        parser.error('Calibration requires --thresholds and no --threshold')
    try:
        import math
        for threshold in args.thresholds or [args.threshold]:
            decision({}, threshold, args.margin)
        if not math.isfinite(args.ram_gib) or args.ram_gib <= 0 or args.output.exists():
            raise ValueError('Invalid output or RAM')
        report = run(args)
        with args.output.open('x', encoding='utf-8') as stream:
            json.dump(report, stream, indent=2, allow_nan=False)
        print('Aggregate evaluation saved. No images, vectors or participant IDs exported.')
        return 0
    except Exception:
        print('Evaluation failed. Check consent, manifest, image quality, model and output directory. No payload logged.', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
