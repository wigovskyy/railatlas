// Вход через GitHub для RailAtlas. Cloudflare Worker, который делает то, что статическая
// страница на GitHub Pages не может: обменивает код авторизации на токен с помощью секрета приложения.
//
// Переменные окружения воркера (Settings → Variables and Secrets):
//   GITHUB_CLIENT_ID      — Client ID приложения GitHub
//   GITHUB_CLIENT_SECRET  — Client secret приложения GitHub (тип Secret)
//   SITE_URL              — адрес карты, например https://wigovskyy.github.io/railatlas/
//
// В настройках приложения GitHub адрес Callback URL: https://<ваш-воркер>.workers.dev/callback

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const site = new URL(env.SITE_URL);

    if (url.pathname === '/login') {
      // Возвращаемся только на страницы самой карты, чтобы токен не утёк на чужой сайт
      const back = safeReturn(url.searchParams.get('return'), site);
      const state = crypto.randomUUID();
      const auth = new URL('https://github.com/login/oauth/authorize');
      auth.searchParams.set('client_id', env.GITHUB_CLIENT_ID);
      auth.searchParams.set('redirect_uri', `${url.origin}/callback`);
      auth.searchParams.set('state', state);
      return new Response(null, {
        status: 302,
        headers: {
          Location: auth.toString(),
          'Set-Cookie': `ra_state=${state}.${btoa(back)}; Path=/callback; Max-Age=600; HttpOnly; Secure; SameSite=Lax`,
        },
      });
    }

    if (url.pathname === '/callback') {
      const cookie = (request.headers.get('Cookie') || '').match(/(?:^|;\s*)ra_state=([^;]+)/);
      const [state, backB64] = cookie ? cookie[1].split('.') : [];
      if (!state || state !== url.searchParams.get('state')) return text('Вход не удался: устаревшая ссылка. Вернитесь на карту и нажмите «Войти» ещё раз.', 400);
      let back;
      try { back = safeReturn(atob(backB64), site); } catch (e) { back = site.toString(); }
      const res = await fetch('https://github.com/login/oauth/access_token', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'User-Agent': 'railatlas-auth' },
        body: JSON.stringify({ client_id: env.GITHUB_CLIENT_ID, client_secret: env.GITHUB_CLIENT_SECRET, code: url.searchParams.get('code'), redirect_uri: `${url.origin}/callback` }),
      });
      const data = await res.json().catch(() => ({}));
      if (!data.access_token) return text('GitHub не выдал токен: ' + (data.error_description || data.error || res.status), 400);
      // Токен передаём в части адреса после #: она не уходит на сервер GitHub Pages и не попадает в логи
      return new Response(null, {
        status: 302,
        headers: { Location: `${back}#gh_token=${encodeURIComponent(data.access_token)}`, 'Set-Cookie': 'ra_state=; Path=/callback; Max-Age=0' },
      });
    }

    return text('RailAtlas: вход через GitHub', 200);
  },
};

function safeReturn(value, site) {
  try {
    const u = new URL(value);
    if (u.origin === site.origin && u.pathname.startsWith(site.pathname)) { u.hash = ''; return u.toString(); }
  } catch (e) { /* неверный адрес */ }
  return site.toString();
}

function text(body, status) {
  return new Response(body, { status, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
}
