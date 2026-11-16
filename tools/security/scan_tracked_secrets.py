"""Scan Git's tracked tree/index without printing matched values. Standard library only."""
import argparse
import os
import pathlib
import re
import subprocess
import sys

PATTERNS = {
    'private-key': re.compile(rb'-----BEGIN (?:[A-Z0-9]+ )*PRIVATE KEY-----'),
    'github-token': re.compile(rb'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{60,})\b'),
    'aws-access-key': re.compile(rb'\b(?:AKIA|ASIA)[A-Z0-9]{16}\b'),
}


def inspect(chunks):
    """Keep a bounded overlap so a marker across read boundaries is detected."""
    previous = b''
    for chunk in chunks:
        window = previous + chunk
        for rule, pattern in PATTERNS.items():
            if pattern.search(window):
                return rule
        previous = window[-256:]
    return None


def tracked_paths(root, staged=False):
    command = ['git', 'diff', '--cached', '--name-only', '--diff-filter=ACMRT', '-z'] if staged else ['git', 'ls-files', '-z']
    result = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, check=True)
    return [path.decode('utf-8', 'surrogateescape') for path in result.stdout.split(b'\0') if path]


def scan(root, staged=False):
    violations = []
    for name in tracked_paths(root, staged):
        if staged:
            with subprocess.Popen(['git', 'show', ':' + name], cwd=root, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL) as process:
                rule = inspect(iter(lambda: process.stdout.read(1024 * 1024), b''))
                if rule:
                    # Drain the pipe before waiting, avoiding a blocked producer.
                    for _ in iter(lambda: process.stdout.read(1024 * 1024), b''):
                        pass
                status = process.wait()
                if status:
                    raise RuntimeError('Could not read staged tracked path: ' + name)
        else:
            path = root / name
            if path.is_symlink():
                rule = inspect([os.readlink(path).encode('utf-8', 'surrogateescape')])
                if rule:
                    violations.append((name, rule))
                continue
            if not path.is_file():
                continue
            try:
                path.resolve().relative_to(root.resolve())
            except ValueError:
                raise RuntimeError('Tracked path resolves outside this repository: ' + name)
            with path.open('rb') as stream:
                rule = inspect(iter(lambda: stream.read(1024 * 1024), b''))
        if rule:
            violations.append((name, rule))
    return violations


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--staged', action='store_true', help='Read staged added/modified paths from the index, not unstaged working files')
    args = parser.parse_args()
    root = pathlib.Path(subprocess.check_output(['git', 'rev-parse', '--show-toplevel'], text=True).strip())
    violations = scan(root, args.staged)
    for name, rule in violations:
        print('Blocked tracked secret material: {} [{}]; value suppressed'.format(name, rule), file=sys.stderr)
    if violations:
        return 1
    print('Tracked secret patterns: clean (private keys, GitHub tokens, AWS access IDs).')
    return 0


if __name__ == '__main__':
    sys.exit(main())
