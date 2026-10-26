# Вход через GitHub для карты на GitHub Pages

Смотреть карту может кто угодно. Чтобы правки сохранялись в репозиторий, владелец входит через GitHub: кнопка «войти через GitHub» в статусе внизу панели. Страница на GitHub Pages не может сама завершить вход (для этого нужен секрет приложения), поэтому этот шаг делает маленький бесплатный Cloudflare Worker из `worker.js`.

## 1. Воркер на Cloudflare

1. Зарегистрируйтесь на dash.cloudflare.com (бесплатный тариф).
2. Workers & Pages → Create → Create Worker. Имя, например, `railatlas-auth`. Deploy.
3. Edit code: замените весь код содержимым `worker.js`, нажмите Deploy.
4. Запишите адрес воркера, вида `https://railatlas-auth.<ваше-имя>.workers.dev`.

## 2. Приложение GitHub

1. github.com → Settings → Developer settings → GitHub Apps → New GitHub App.
2. GitHub App name: любое, например `RailAtlas editor`. Homepage URL: `https://wigovskyy.github.io/railatlas/`.
3. Callback URL: `https://railatlas-auth.<ваше-имя>.workers.dev/callback`.
4. Webhook: снимите галочку Active.
5. Permissions → Repository permissions → Contents: **Read and write**. Остальное не трогайте.
6. Where can this GitHub App be installed: Only on this account. Create GitHub App.
7. На странице приложения скопируйте **Client ID** и нажмите **Generate a new client secret**, скопируйте секрет (он показывается один раз).
8. Слева Install App → Install → Only select repositories → `railatlas`.

Приложение может менять только этот репозиторий и только у тех, кто сам имеет в нём право записи. Вход действует 8 часов, потом карта попросит войти снова. Если это неудобно, в настройках приложения в разделе Optional features отключите срок действия токенов (User-to-server token expiration → Opt-out).

## 3. Настройки воркера

В Cloudflare: воркер → Settings → Variables and Secrets → Add:

| Имя | Тип | Значение |
| --- | --- | --- |
| `GITHUB_CLIENT_ID` | Text | Client ID из шага 2.7 |
| `GITHUB_CLIENT_SECRET` | Secret | секрет из шага 2.7 |
| `SITE_URL` | Text | `https://wigovskyy.github.io/railatlas/` |

Deploy.

## 4. Подключить к карте

Адрес воркера этого проекта уже вписан в `index.html` (`ghAuthUrl`). Если вы сделаете свой воркер, замените его на свой адрес. Пока адрес пустой, вместо входа карта просит вставить токен вручную.
