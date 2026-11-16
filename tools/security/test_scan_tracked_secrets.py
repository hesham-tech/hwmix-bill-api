import pathlib
import subprocess
import tempfile
import unittest

from scan_tracked_secrets import inspect, scan


class SecretScanTests(unittest.TestCase):
    def test_private_key_header_is_found_across_chunk_boundaries(self):
        header = b'-----BEGIN ' + b'OPENSSH ' + b'PRIVATE KEY-----'
        self.assertEqual('private-key', inspect([b'prefix' + header[:14], header[14:] + b'\nsynthetic']))

    def test_secret_references_and_public_keys_are_allowed(self):
        self.assertIsNone(inspect([b'${{ secrets.PRODUCTION_SSH_PRIVATE_KEY }}\nssh-ed25519 public-example']))

    def test_known_token_patterns_are_detected_without_real_credentials(self):
        self.assertEqual('github-token', inspect([b'gh' + b'p_' + b'x' * 40]))
        self.assertEqual('aws-access-key', inspect([b'AK' + b'IA' + b'X' * 16]))

    def test_index_is_scanned_even_if_worktree_hides_the_staged_secret(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            subprocess.run(['git', 'init', '-q'], cwd=root, check=True)
            path = root / 'fixture.txt'
            path.write_bytes(b'-----BEGIN ' + b'RSA ' + b'PRIVATE KEY-----\nsynthetic')
            subprocess.run(['git', 'add', 'fixture.txt'], cwd=root, check=True)
            path.write_text('clean worktree')
            self.assertEqual([], scan(root))
            self.assertEqual([('fixture.txt', 'private-key')], scan(root, staged=True))
            # Untracked credentials are not read or disclosed by this repository check.
            (root / 'untracked.txt').write_bytes(b'-----BEGIN ' + b'OPENSSH ' + b'PRIVATE KEY-----')
            path.write_text('clean index')
            subprocess.run(['git', 'add', 'fixture.txt'], cwd=root, check=True)
            self.assertEqual([], scan(root, staged=True))


if __name__ == '__main__':
    unittest.main()
