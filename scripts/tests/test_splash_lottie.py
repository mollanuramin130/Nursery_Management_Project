"""Regression checks for the animation packaged by both mobile apps."""
import importlib.util
import json
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / 'build_splash_lottie.py'
spec = importlib.util.spec_from_file_location('splash_builder', SCRIPT)
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


def animated_tracks(value):
    if isinstance(value, dict):
        if value.get('a') == 1 and isinstance(value.get('k'), list):
            yield value['k']
        for child in value.values():
            yield from animated_tracks(child)
    elif isinstance(value, list):
        for child in value:
            yield from animated_tracks(child)


class SplashAssetTest(unittest.TestCase):
    def test_bundled_assets_have_no_empty_animated_tracks(self):
        for path in builder.OUT_PATHS:
            with self.subTest(app=str(path)):
                tracks = list(animated_tracks(json.loads(path.read_text())))
                self.assertTrue(tracks)
                self.assertTrue(all(tracks), 'Empty keyframes crash Lottie Flutter')

    def test_future_keyframes_survive_clipping_without_mutating_source(self):
        source = {'op': 200, 'ks': {'o': {'a': 1, 'k': [
            {'t': 149, 's': [100]}, {'t': 150, 's': [0]}
        ]}}}
        trimmed = builder.trim_layer(source)
        self.assertEqual(trimmed['op'], builder.END_FRAME)
        self.assertEqual(trimmed['ks'], source['ks'])
        self.assertEqual(source['op'], 200)

    def test_generated_assets_match_and_keep_original_interpolation(self):
        result = builder.build()
        original = json.loads(builder.SRC.read_text())
        kept = [layer for layer in original['layers'] if builder.should_keep(layer)]
        self.assertEqual([layer['ks'] for layer in result['layers']],
                         [layer['ks'] for layer in kept])
        self.assertEqual(result['op'], builder.END_FRAME)
        for path in builder.OUT_PATHS:
            self.assertEqual(json.loads(path.read_text()), result)


if __name__ == '__main__':
    unittest.main()
