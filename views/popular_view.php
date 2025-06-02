<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Popular Todas as Tabelas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="mb-4">🚀 Popular Todas as Tabelas</h1>

        <form id="popularForm">
            <div class="mb-3">
                <label for="total" class="form-label">Quantidade de registros por tabela:</label>
                <input type="number" name="total" id="total" class="form-control" value="1000" min="1" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" id="btnPopular">
                ▶️ Popular Todas
            </button>
        </form>

        <div id="loadingSpinner" class="mt-4 text-center d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2">Carregando registros, por favor aguarde...</p>
        </div>

        <div id="mensagemResultado" class="mt-4 fs-5"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            console.log('✅ DOM carregado');

            const form = document.getElementById('popularForm');
            const btn = document.getElementById('btnPopular');
            const spinner = document.getElementById('loadingSpinner');
            const resultado = document.getElementById('mensagemResultado');

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                console.log('📨 Formulário enviado');

                const total = document.getElementById('total').value;
                if (btn.disabled) {
                    console.log('⛔ Botão já desativado, ignorando...');
                    return;
                }

                btn.disabled = true;
                spinner.classList.remove('d-none');
                resultado.innerHTML = '';
                console.log('⏳ Spinner ativado e botão desativado');

                try {
                    const res = await fetch(`PopularController.php?acao=popular&total=${encodeURIComponent(total)}`);
                    const text = await res.text();
                    console.log('[DEBUG] Resposta recebida:', text);
                    try {
                        const json = JSON.parse(text);
                        if (json.sucesso) {
                            resultado.innerHTML = `<div class="alert alert-success">${json.mensagem}</div>`;
                        } else {
                            resultado.innerHTML = `<div class="alert alert-danger">Erro: ${json.erro}</div>`;
                        }
                    } catch (err) {
                        console.error('[ERRO] Resposta não é JSON:', err, 'Resposta:', text);
                        resultado.innerHTML = `<div class="alert alert-danger">Erro: Resposta inválida do servidor</div>`;
                    }
                } catch (err) {
                    console.error('❌ Erro no fetch:', err);
                    resultado.innerHTML = `<div class="alert alert-danger">Erro inesperado ao carregar: ${err.message}</div>`;
                }

                spinner.classList.add('d-none');
                btn.disabled = false;
                console.log('✅ Finalizado: spinner escondido e botão reativado');
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>