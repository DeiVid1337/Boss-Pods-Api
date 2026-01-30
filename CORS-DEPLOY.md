# CORS em produção (deploy)

## Por que aparece "CORS Missing Allow Origin" com status 502?

**502** significa que a resposta **não vem do Laravel** — vem do proxy (Railway). Quando o proxy devolve 502, ele não envia headers CORS, então o navegador mostra "CORS Missing Allow Origin" mesmo que o problema seja o **serviço não estar respondendo**.

**Causa comum na Railway:** o container usa **php-fpm**, que **não escuta HTTP** na porta que a Railway usa. A Railway espera algo escutando em `$PORT` (ex.: 8000). O php-fpm escuta só FastCGI (porta 9000), então o proxy não consegue falar com o app → **502**.

**Solução:** o **Dockerfile** já usa como comando padrão `sh railway/start.sh`, então o container sobe o Laravel escutando HTTP na variável `$PORT`. O 502 some se o deploy usar essa imagem sem sobrescrever o comando.

- **Docker:** comando padrão da imagem = `sh railway/start.sh` (Railway usa isso).
- **Local (docker-compose):** o `docker-compose.yml` sobrescreve com `php-fpm` para o nginx usar.

---

## 1. API (Railway / servidor)

### 1.1 Porta e comando (evitar 502)

O script `railway/start.sh` (executado pelo CMD do Dockerfile) faz:
- `php artisan migrate --force` (se der)
- `php artisan config:cache`
- `php artisan serve --host=0.0.0.0 --port=$PORT`

Assim o app escuta HTTP na porta que a Railway usa. **Não é necessário** configurar Start Command no dashboard; se tiver definido algo diferente, remova para usar o padrão da imagem.

### 1.2 Banco (Postgres na Railway)

Para evitar o erro *"invalid integer value \"client_encoding='utf8'\" for connection option port"*, use **variáveis explícitas** no serviço da API:

No serviço **Boss-Pods-Api** → **Variables**, adicione (referenciando o serviço Postgres):

| Variável       | Valor (referência Railway)   |
|----------------|------------------------------|
| `DB_CONNECTION` | `pgsql`                      |
| `DB_HOST`      | `${{Postgres.PGHOST}}`       |
| `DB_PORT`      | `${{Postgres.PGPORT}}`       |
| `DB_DATABASE`  | `${{Postgres.PGDATABASE}}`   |
| `DB_USERNAME`  | `${{Postgres.PGUSER}}`       |
| `DB_PASSWORD`  | `${{Postgres.PGPASSWORD}}`   |

Assim a API usa host/port/banco/usuário/senha direto, sem parse da `DATABASE_URL`. A `DATABASE_URL` pode continuar definida; o Laravel prefere `DB_*` quando existem.

### 1.3 CORS no .env

No **.env da API** em produção, defina a origem do frontend:

```env
# Origens permitidas (separadas por vírgula). Use * só em dev.
CORS_ALLOWED_ORIGINS=https://boss-front-eight.vercel.app
```

Se tiver mais origens (ex.: previews da Vercel):

```env
CORS_ALLOWED_ORIGINS=https://boss-front-eight.vercel.app,https://*.vercel.app
```

**Nota:** Com `*` em `allowed_origins` o Laravel já envia CORS para qualquer origem. Se mesmo assim der CORS em produção, use origem explícita como acima.

Depois de alterar o .env:

```bash
php artisan config:clear
# Se usar cache de config em produção:
# php artisan config:cache
```

## 2. Frontend (Vercel)

A **base URL da API** deve ser a **URL pública** da API (ex.: Railway), não um hostname interno.

- **Errado:** `https://boss-front-eight.vercel.app/boss-pods-api.railway.internal/...`  
  (isso faz a requisição ir para o Vercel, não para a API.)
- **Certo:** `https://SEU-PROJETO.up.railway.app/api/v1/...`  
  (substitua pela URL real do app no Railway.)

No frontend, configure a variável de ambiente (ex. na Vercel):

```env
VITE_API_URL=https://SEU-PROJETO.up.railway.app
```

e use essa variável em todas as chamadas à API.

## 3. Checklist

- [ ] **Railway:** não sobrescrever o comando do container (a imagem já usa `railway/start.sh`; app escuta na PORT).
- [ ] API: `.env` com `CORS_ALLOWED_ORIGINS=https://boss-front-eight.vercel.app` (ou suas origens).
- [ ] API: `config/cors.php` com `allowed_origins` vindo do env (já configurado).
- [ ] Frontend: base URL da API apontando para a URL pública do Railway (não `railway.internal`).
- [ ] Depois de mudar .env na API: `php artisan config:clear` (o `railway/start.sh` já roda `config:cache` no deploy).

## 4. Se ainda der 502 (serviço não responde)

O CORS está certo na Railway; o 502 indica que **nada está escutando na porta** que o proxy usa. Confira:

1. **Start Command na Railway**  
   Dashboard → seu serviço da API → **Settings** → **Deploy** → **Start Command**:
   - **Deixe em branco** (para usar o comando padrão da imagem: `sh railway/start.sh`), **ou**
   - Defina exatamente: `sh railway/start.sh`  
   Se estiver algo como `php-fpm` ou outro comando, **apague** e salve.

2. **Logs do deploy**  
   Em **Deployments** → último deploy → **View logs**. Verifique se o container sobe com o script e se aparece algo como o Laravel escutando na porta (ex.: `Listening on http://0.0.0.0:XXXX`). Se o script falhar (migrate, config, etc.), o processo para e o proxy devolve 502.

3. **Redeploy**  
   Depois de alterar o Start Command ou de garantir que a imagem usada é a do último push, faça **Redeploy** (Deployments → ⋮ → Redeploy) para subir de novo com a configuração correta.

## 5. Se ainda falhar (CORS / rede)

1. Abra o DevTools → **Network** e veja a requisição que falha (POST ou OPTIONS).
2. Confira a **URL** da requisição: deve ser a do Railway, não do Vercel.
3. Confira a **resposta** (headers): deve ter `Access-Control-Allow-Origin` com a origem do front.
4. Se o **OPTIONS** (preflight) retornar 404/405, o request pode não estar chegando na aplicação (proxy/porta no Railway).
