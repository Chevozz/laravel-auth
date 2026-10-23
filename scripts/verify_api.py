"""HTTP smoke test; server must run on localhost. No secrets printed/saved.
Creates unique verification user (left for audit) and deletes its product/tokens.
Run: python scripts/verify_api.py
"""
import base64
import json
import secrets
import urllib.error
import urllib.request
import uuid

BASE = 'http://127.0.0.1:8000'
RESULTS = []
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')


def call(method, path, expected, body=None, token=None, upload=False):
    headers = {'Accept': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    if upload:
        boundary = uuid.uuid4().hex
        parts = []
        for key, value in body.items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="check.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + PNG + b'\r\n')
        data = b''.join(parts) + f'--{boundary}--\r\n'.encode()
        headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
    else:
        data = json.dumps(body).encode() if body is not None else None
        headers['Content-Type'] = 'application/json'
    req = urllib.request.Request(BASE + path if path.startswith('/') else path, data, headers, method=method)
    try:
        response = urllib.request.urlopen(req)
    except urllib.error.HTTPError as error:
        response = error
    raw = response.read()
    allowed = expected if isinstance(expected, tuple) else (expected,)
    assert response.status in allowed, f'{method} {path}: expected {expected}, got {response.status}'
    RESULTS.append(f'{method} {path}: {response.status}')
    return json.loads(raw) if 'application/json' in response.headers.get('Content-Type', '') else raw


def main():
    call('GET', '/up', 200)
    call('GET', '/api/products', 401)
    suffix = uuid.uuid4().hex
    password = secrets.token_urlsafe(24)
    account = {'name': 'Verifikasi HTTP', 'email': f'http-{suffix}@example.test', 'password': password, 'password_confirmation': password}
    registered = call('POST', '/api/register', 201, account)['data']
    token1 = registered['token']
    assert 'password' not in registered['user']
    call('POST', '/api/register', 422, account)
    call('POST', '/api/login', 422, {'email': account['email'], 'password': 'wrong-password'})
    token = call('POST', '/api/login', 200, account)['data']['token']
    profile = call('GET', '/api/me', 200, token=token)['data']
    assert profile['email'] == account['email'] and 'password' not in profile
    categories = call('GET', '/api/categories', 200, token=token)['data']
    products = call('GET', '/api/products', 200, token=token)['data']
    assert len(categories) == 5 and len(products) == 12
    assert all(p['category_id'] in {c['id'] for c in categories} for p in products)
    for product in products:
        assert b'<svg' in call('GET', product['image'], 200)
    call('POST', '/api/products', 422, {'price': -1}, token)
    body = {'name': 'Produk verifikasi', 'slug': 'http-' + suffix, 'price': '15000.50', 'stock': 2, 'category_id': categories[0]['id']}
    created = call('POST', '/api/products', 201, body, token, upload=True)['data']
    path = '/api/products/' + str(created['id'])
    image1 = created['image']
    assert call('GET', image1, 200) == PNG
    assert call('GET', path, 200, token=token)['data']['price'] == '15000.50'
    assert call('PATCH', path, 200, {'stock': 3, 'details': ['Diuji melalui HTTP']}, token)['data']['image'] == image1
    call('PATCH', path, 422, {'category_id': 999999999}, token)
    call('PATCH', path, 422, {'price': -1}, token)
    call('PUT', path, 200, body, token)
    replaced = call('POST', path, 200, {**body, '_method': 'PUT'}, token, upload=True)['data']
    image2 = replaced['image']
    assert image2 != image1 and call('GET', image2, 200) == PNG
    call('GET', image1, (403, 404))
    from pathlib import Path
    assert not (Path(__file__).resolve().parents[1] / 'storage/app/public' / image1.split('/storage/', 1)[1]).exists()
    call('DELETE', path, 200, token=token)
    call('GET', path, 404, token=token)
    call('GET', image2, (403, 404))
    from pathlib import Path
    assert not (Path(__file__).resolve().parents[1] / 'storage/app/public' / image2.split('/storage/', 1)[1]).exists()
    call('POST', '/api/logout', 200, token=token)
    call('GET', '/api/me', 401, token=token)
    call('GET', '/api/me', 200, token=token1)
    call('POST', '/api/logout', 200, token=token1)
    call('GET', '/api/me', 401, token=token1)
    print('\n'.join(RESULTS))
    print(f'PASS: {len(RESULTS)} HTTP checks; 12 seed images accessible; tokens revoked. Verification account retained without active token.')


if __name__ == '__main__':
    main()
