<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Índices - Desempenho de Banco de Dados</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Índices - Desempenho de Banco de Dados</h1>
            <button id="themeToggle" class="btn btn-outline-secondary">
                <i class="bi bi-moon-fill"></i> Dark Mode
            </button>
        </div>

        <div class="row mb-3">
            <div class="col-md-4">
                <label for="tabela" class="form-label">Tabela:</label>
                <select id="tabela" class="form-select">
                    <option value="vendas">vendas</option>
                    <option value="pedidos">pedidos</option>
                    <option value="produtos">produtos</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tipoConsulta" class="form-label">Tipo de Consulta:</label>
                <select id="tipoConsulta" class="form-select">
                    <option value="data">Por Data</option>
                    <option value="cliente">Por Cliente</option>
                    <option value="vendedor_data">Por Vendedor + Data</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Colunas para Índice:</label>
                <div id="colunasIndice" class="mt-1"></div>
            </div>
        </div>

        <div class="mb-3">
            <div id="statusIndice" class="mb-2"></div>
            <div id="botoesAcoes">
                <button onclick="popularTodas()" class="btn btn-primary me-2">
                    <i class="bi bi-database-fill"></i> Popular Todas
                </button>
                <button onclick="executarSemIndiceComRemocao()" class="btn btn-danger me-2">
                    <i class="bi bi-x-circle"></i> Sem Índice
                </button>
                <button onclick="executarFluxo('com')" class="btn btn-success me-2">
                    <i class="bi bi-check-circle"></i> Com Índice
                </button>
                <button id="btnTestarInsert" onclick="testarInsert()" class="btn btn-warning me-2" style="display: none;">
                    <i class="bi bi-plus-circle"></i> Testar INSERT
                </button>
                <button onclick="resetarGrafico()" class="btn btn-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Resetar Gráfico
                </button>
            </div>
        </div>

        <div id="loadingSpinner" class="d-none text-center mb-3">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p id="loadingMessage">Carregando...</p>
        </div>

        <div id="resposta" class="mb-4"></div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-bar-chart-line"></i> Comparação de Desempenho</h5>
                <canvas id="graficoDesempenho"></canvas>
                <div id="melhoriaTexto" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script>
        const colsByTable = {
            vendas: ['data_venda', 'cliente_id', 'vendedor_id'],
            pedidos: ['data_pedido', 'cliente_id'],
            produtos: ['nome_produto']
        };

        const validColsByConsulta = {
            vendas: {
                data: ['data_venda'],
                cliente: ['cliente_id'],
                vendedor_data: ['vendedor_id', 'data_venda']
            },
            pedidos: {
                data: ['data_pedido'],
                cliente: ['cliente_id'],
                vendedor_data: []
            },
            produtos: {
                data: [],
                cliente: [],
                vendedor_data: [],
                nome: ['nome_produto']
            }
        };

        let tabela = 'vendas';
        let tipoConsulta = 'data';
        let tempos = { sem: null, com: null, insertSem: null, insertCom: null };
        let grafico;

        function buildCheckboxes() {
            const div = document.getElementById('colunasIndice');
            div.innerHTML = '';

            colsByTable[tabela].forEach((col) => {
                div.innerHTML += `
                    <div class="form-check d-inline-block me-3" id="wrapper_${col}">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="col_${col}" 
                            value="${col}"
                            ${tabela === 'vendas' && col === 'data_venda' && tipoConsulta === 'data' ? 'checked' : ''}
                            ${tabela === 'produtos' && col === 'nome_produto' && tipoConsulta === 'nome' ? 'checked' : ''}
                        >
                        <label class="form-check-label" for="col_${col}">${col}</label>
                    </div>`;
            });

            atualizarCheckboxesPorConsulta();
            atualizarBotoes();
        }

        function atualizarCheckboxesPorConsulta() {
            const validCols = validColsByConsulta[tabela][tipoConsulta] || [];

            colsByTable[tabela].forEach(col => {
                const chk = document.getElementById(`col_${col}`);
                const wrapper = document.getElementById(`wrapper_${col}`);

                if (validCols.includes(col)) {
                    chk.disabled = false;
                    if ((tabela === 'vendas' && col === 'data_venda' && tipoConsulta === 'data') ||
                        (tabela === 'produtos' && col === 'nome_produto' && tipoConsulta === 'nome')) {
                        chk.checked = true;
                    }
                    wrapper.classList.remove('checkbox-desabilitado');
                } else {
                    chk.disabled = true;
                    chk.checked = false;
                    wrapper.classList.add('checkbox-desabilitado');
                }
            });
        }

        function getColunas() {
            return Array.from(document.querySelectorAll('#colunasIndice input:checked'))
                .map(el => el.value).join(',');
        }

        function updateStatus(existe) {
            document.getElementById('statusIndice').innerHTML = existe
                ? `<div class="alert alert-success d-inline-block"><i class="bi bi-check-circle-fill"></i> Índice criado</div>`
                : `<div class="alert alert-danger d-inline-block"><i class="bi bi-x-circle-fill"></i> Índice não criado</div>`;
        }

        function atualizarBotoes() {
            const btnTestarInsert = document.getElementById('btnTestarInsert');
            if (tabela === 'pedidos') {
                btnTestarInsert.style.display = 'inline-block';
            } else {
                btnTestarInsert.style.display = 'none';
            }
        }

        async function executarFluxo(tipo) {
            const spinner = document.getElementById('loadingSpinner');
            const loadingMessage = document.getElementById('loadingMessage');
            const buttons = document.querySelectorAll('#botoesAcoes button');
            spinner.classList.remove('d-none');
            spinner.classList.add('d-block');
            loadingMessage.textContent = 'Executando consulta, aguarde...';
            buttons.forEach(btn => btn.disabled = true);

            try {
                const paramsBase = new URLSearchParams({
                    table: tabela,
                    tipoConsulta,
                    colunas: getColunas(),
                    execucoes: 10
                });

                console.log(`[INÍCIO] executarFluxo(${tipo})`);
                console.log('Tabela:', tabela);
                console.log('Colunas selecionadas:', getColunas());
                console.log('Tipo de consulta:', tipoConsulta);

                if (tipo === 'com') {
                    console.log(`[DEBUG] Fetching: processa_acao.php?acao=criar&${paramsBase}`);
                    let res = await fetch(`processa_acao.php?acao=criar&${paramsBase}`);
                    let text = await res.text();
                    console.log('[DEBUG] Resposta (criar):', text);

                    try {
                        const d = JSON.parse(text);
                        if (d.erro) {
                            console.error('[ERRO] Erro na criação do índice:', d.erro);
                            alert(`❌ Erro: ${d.erro}`);
                            return;
                        }
                        if (d.mensagem) {
                            document.getElementById('resposta').innerHTML = `<div class="alert alert-info">${d.mensagem}</div>`;
                        }
                    } catch (err) {
                        console.error('[ERRO] Falha ao interpretar JSON (criar):', err, 'Resposta:', text);
                        alert('❌ Erro ao processar criação de índice. Verifique o console.');
                        return;
                    }
                }

                console.log('[DEBUG] Executando consulta...');
                let res = await fetch(`processa_acao.php?acao=executar&${paramsBase}`);
                let text = await res.text();
                console.log('[DEBUG] Resposta (executar):', text);

                let d;
                try {
                    d = JSON.parse(text);
                    if (d.erro) {
                        console.error('[ERRO] Erro na execução da consulta:', d.erro);
                        alert(`❌ Erro: ${d.erro}`);
                        return;
                    }
                } catch (err) {
                    console.error('[ERRO] Falha ao interpretar JSON (executar):', err, 'Resposta:', text);
                    alert('❌ Erro ao processar a consulta. Verifique o console.');
                    return;
                }

                let html = '';
                if (d.mensagem) {
                    html += `<div class="alert alert-info">${d.mensagem}</div>`;
                }
                html += gerarTabelaResultados(d);

                document.getElementById('resposta').innerHTML = html;

                updateStatus(d.indice_existente);
                tempos[tipo] = d.tempo_medio;
                if (!grafico) initChart();
                else updateChart();
            } finally {
                spinner.classList.remove('d-block');
                spinner.classList.add('d-none');
                buttons.forEach(btn => btn.disabled = false);
            }
        }

        async function executarSemIndiceComRemocao() {
            const spinner = document.getElementById('loadingSpinner');
            const loadingMessage = document.getElementById('loadingMessage');
            const buttons = document.querySelectorAll('#botoesAcoes button');
            spinner.classList.remove('d-none');
            spinner.classList.add('d-block');
            loadingMessage.textContent = 'Removendo índices e executando consulta, aguarde...';
            buttons.forEach(btn => btn.disabled = true);

            try {
                const res = await fetch(`processa_acao.php?acao=remover_todos&table=${tabela}`);
                const text = await res.text();
                console.log('[DEBUG] Remoção de índice:', text);
                const data = JSON.parse(text);
                if (data.mensagem) {
                    document.getElementById('resposta').innerHTML =
                        `<div class="alert alert-info">${data.mensagem}</div>`;
                }
            } catch (err) {
                console.error('[ERRO] Falha ao remover índices:', err);
                alert('❌ Erro ao remover índices antes da consulta sem índice.');
            } finally {
                await executarFluxo('sem');
            }
        }

        async function testarInsert() {
            const spinner = document.getElementById('loadingSpinner');
            const loadingMessage = document.getElementById('loadingMessage');
            const buttons = document.querySelectorAll('#botoesAcoes button');
            
            console.log('[DEBUG] Ativando spinner para Testar INSERT');
            spinner.classList.remove('d-none');
            spinner.classList.add('d-block');
            loadingMessage.textContent = 'Testando INSERT, aguarde...';
            buttons.forEach(btn => btn.disabled = true);

            try {
                // Garantir que o spinner seja visível por pelo menos 500 ms
                const startTime = Date.now();
                
                // Testar INSERT sem índice
                let res = await fetch(`processa_acao.php?acao=testar_insert&table=pedidos&colunas=${getColunas()}&usarIndice=false`);
                let text = await res.text();
                console.log('[DEBUG] Resposta (testar_insert sem índice):', text);
                let dataSemIndice = JSON.parse(text);
                if (dataSemIndice.erro) {
                    console.error('[ERRO] Erro no teste de INSERT sem índice:', dataSemIndice.erro);
                    alert(`❌ Erro: ${dataSemIndice.erro}`);
                    return;
                }

                // Criar índice, se necessário
                if (!dataSemIndice.indice_existente) {
                    res = await fetch(`processa_acao.php?acao=criar&table=pedidos&colunas=${getColunas()}`);
                    text = await res.text();
                    console.log('[DEBUG] Resposta (criar índice para INSERT):', text);
                    let criarData = JSON.parse(text);
                    if (criarData.erro) {
                        console.error('[ERRO] Erro na criação do índice para INSERT:', criarData.erro);
                        alert(`❌ Erro: ${criarData.erro}`);
                        return;
                    }
                }

                // Testar INSERT com índice
                res = await fetch(`processa_acao.php?acao=testar_insert&table=pedidos&colunas=${getColunas()}&usarIndice=true`);
                text = await res.text();
                console.log('[DEBUG] Resposta (testar_insert com índice):', text);
                let dataComIndice = JSON.parse(text);
                if (dataComIndice.erro) {
                    console.error('[ERRO] Erro no teste de INSERT com índice:', dataComIndice.erro);
                    alert(`❌ Erro: ${dataComIndice.erro}`);
                    return;
                }

                // Exibir resultados
                let html = `
                    <div class="card mb-4"><div class="card-body">
                        <h5 class="card-title"><i class="bi bi-plus-circle"></i> Resultado Teste de INSERT</h5>
                        <p><strong>Tempo médio (Sem Índice):</strong> ${dataSemIndice.tempo_medio} ms</p>
                        <p><strong>Tempo médio (Com Índice):</strong> ${dataComIndice.tempo_medio} ms</p>
                    </div></div>`;
                document.getElementById('resposta').innerHTML = html;

                updateStatus(dataComIndice.indice_existente);
                tempos.insertSem = dataSemIndice.tempo_medio;
                tempos.insertCom = dataComIndice.tempo_medio;
                if (!grafico) initChart();
                else updateChart();

                // Garantir que o spinner seja visível por pelo menos 500 ms
                const elapsedTime = Date.now() - startTime;
                if (elapsedTime < 500) {
                    await new Promise(resolve => setTimeout(resolve, 500 - elapsedTime));
                }
            } catch (err) {
                console.error('[ERRO] Erro ao testar INSERT:', err);
                alert('❌ Erro ao testar INSERT. Verifique o console.');
            } finally {
                console.log('[DEBUG] Desativando spinner para Testar INSERT');
                spinner.classList.remove('d-block');
                spinner.classList.add('d-none');
                buttons.forEach(btn => btn.disabled = false);
            }
        }

        function gerarTabelaResultados(data) {
            let html = `<div class="card mb-4"><div class="card-body">
                <h5 class="card-title"><i class="bi bi-graph-up"></i> Resultado</h5>
                <p><strong>Tempo médio:</strong> ${data.tempo_medio} ms</p>
            </div></div>`;

            html += `<h5><i class="bi bi-search"></i> Plano de Execução (EXPLAIN)</h5>
                <table class="table table-bordered"><thead class="table-secondary"><tr>
                    <th>ID</th><th>Type</th><th>Table</th><th>Key</th><th>Rows</th><th>Extra</th>
                </tr></thead><tbody>`;

            const explainRows = Array.isArray(data.explain) ? data.explain : [data.explain];
            explainRows.forEach(row => {
                if (row) {
                    html += `<tr>
                        <td>${row.id || ''}</td>
                        <td>${row.type || ''}</td>
                        <td>${row.table || ''}</td>
                        <td>${row.key || ''}</td>
                        <td>${row.rows || ''}</td>
                        <td>${row.Extra || ''}</td>
                    </tr>`;
                }
            });

            html += `</tbody></table>`;
            return html;
        }

        function popularTodas(total = 250000) {
            console.log('[DEBUG] Iniciando popularTodas()');

            const params = new URLSearchParams({ acao: 'popular', total });
            const url = `controllers/PopularController.php?${params}`;

            const spinner = document.getElementById('loadingSpinner');
            const loadingMessage = document.getElementById('loadingMessage');
            const buttons = document.querySelectorAll('#botoesAcoes button');
            spinner.classList.remove('d-none');
            spinner.classList.add('d-block');
            loadingMessage.textContent = 'Populando banco de dados, aguarde...';
            buttons.forEach(btn => btn.disabled = true);

            fetch(url)
                .then(res => res.text())
                .then(text => {
                    console.log('[DEBUG] Resposta recebida:', text);
                    try {
                        const json = JSON.parse(text);
                        if (json.sucesso) {
                            document.getElementById('resposta').innerHTML = `<div class="alert alert-success">${json.mensagem}</div>`;
                        } else {
                            document.getElementById('resposta').innerHTML = `<div class="alert alert-danger">Erro: ${json.erro}</div>`;
                        }
                    } catch (err) {
                        console.error('[ERRO] Resposta não é JSON:', err, 'Resposta:', text);
                        document.getElementById('resposta').innerHTML = `<div class="alert alert-danger">Erro: Resposta inválida do servidor</div>`;
                    }
                    resetChart();
                })
                .catch(err => {
                    console.error('[ERRO] Erro ao popular:', err);
                    document.getElementById('resposta').innerHTML =
                        `<div class="alert alert-danger">Erro: ${err.message}</div>`;
                })
                .finally(() => {
                    spinner.classList.remove('d-block');
                    spinner.classList.add('d-none');
                    buttons.forEach(btn => btn.disabled = false);
                });
        }

        function resetarGrafico() {
            document.getElementById('resposta').innerHTML = `<div class="alert alert-secondary">Gráfico limpo.</div>`;
            resetChart();
        }

        function resetChart() {
            tempos = { sem: null, com: null, insertSem: null, insertCom: null };
            updateStatus(false);
            if (grafico) {
                grafico.data.datasets[0].data = [0, 0, 0, 0];
                grafico.update();
            }
            document.getElementById('melhoriaTexto').innerHTML = '';
        }

        function initChart() {
            const ctx = document.getElementById('graficoDesempenho').getContext('2d');
            const isDarkMode = document.body.getAttribute('data-theme') === 'dark';
            grafico = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Sem Índice (SELECT)', 'Com Índice (SELECT)', 'Sem Índice (INSERT)', 'Com Índice (INSERT)'],
                    datasets: [{
                        label: 'Tempo (ms)',
                        data: [tempos.sem || 0, tempos.com || 0, tempos.insertSem || 0, tempos.insertCom || 0],
                        backgroundColor: isDarkMode 
                            ? ['#ff6b6b', '#51cf66', '#ffca3a', '#8ac926'] 
                            : ['#dc3545', '#28a745', '#ffc107', '#6c757d']
                    }]
                },
                options: {
                    plugins: {
                        datalabels: {
                            color: isDarkMode ? '#f8f9fa' : '#fff',
                            font: { weight: 'bold', size: 16 },
                            formatter: v => v ? v + ' ms' : ''
                        }
                    },
                    scales: { y: { beginAtZero: true } }
                },
                plugins: [ChartDataLabels]
            });
            updateImprovement();
        }

        function updateChart() {
            const isDarkMode = document.body.getAttribute('data-theme') === 'dark';
            grafico.data.datasets[0].data = [tempos.sem || 0, tempos.com || 0, tempos.insertSem || 0, tempos.insertCom || 0];
            grafico.data.datasets[0].backgroundColor = isDarkMode 
                ? ['#ff6b6b', '#51cf66', '#ffca3a', '#8ac926'] 
                : ['#dc3545', '#28a745', '#ffc107', '#6c757d'];
            grafico.options.plugins.datalabels.color = isDarkMode ? '#f8f9fa' : '#fff';
            grafico.update();
            updateImprovement();
        }

        function updateImprovement() {
            let html = '';
            if (tempos.sem && tempos.com) {
                const pctSelect = (((tempos.sem - tempos.com) / tempos.sem) * 100).toFixed(2);
                html += `<p><strong>Melhoria com índice (SELECT):</strong> ${pctSelect}% ${pctSelect >= 0 ? 'mais rápido' : 'mais lento'}</p>`;
            }
            if (tempos.insertSem && tempos.insertCom) {
                const pctInsert = (((tempos.insertSem - tempos.insertCom) / tempos.insertSem) * 100).toFixed(2);
                html += `<p><strong>Impacto com índice (INSERT):</strong> ${Math.abs(pctInsert)}% ${pctInsert >= 0 ? 'mais rápido' : 'mais lento'}</p>`;
            }
            document.getElementById('melhoriaTexto').innerHTML = html;
        }

        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.setAttribute('data-theme', 'dark');
            themeToggle.innerHTML = '<i class="bi bi-sun-fill"></i> Light Mode';
        }

        themeToggle.addEventListener('click', () => {
            const currentTheme = body.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            body.setAttribute('data-theme', newTheme);
            themeToggle.innerHTML = newTheme === 'light' 
                ? '<i class="bi bi-moon-fill"></i> Dark Mode'
                : '<i class="bi bi-sun-fill"></i> Light Mode';
            
            localStorage.setItem('theme', newTheme);
            
            if (grafico) {
                grafico.data.datasets[0].backgroundColor = newTheme === 'light' 
                    ? ['#dc3545', '#28a745', '#ffc107', '#6c757d']
                    : ['#ff6b6b', '#51cf66', '#ffca3a', '#8ac926'];
                grafico.options.plugins.datalabels.color = newTheme === 'light' ? '#fff' : '#f8f9fa';
                grafico.update();
            }
        });

        document.getElementById('tabela').addEventListener('change', e => {
            tabela = e.target.value;
            const tipoConsultaSelect = document.getElementById('tipoConsulta');
            tipoConsultaSelect.innerHTML = '';

            if (tabela === 'produtos') {
                tipoConsultaSelect.innerHTML = `
                    <option value="nome">Por Nome</option>
                `;
                tipoConsulta = 'nome';
            } else {
                tipoConsultaSelect.innerHTML = `
                    <option value="data">Por Data</option>
                    <option value="cliente">Por Cliente</option>
                    <option value="vendedor_data">Por Vendedor + Data</option>
                `;
                tipoConsulta = 'data';
            }
            buildCheckboxes();
        });

        document.getElementById('tipoConsulta').addEventListener('change', e => {
            tipoConsulta = e.target.value;
            atualizarCheckboxesPorConsulta();
        });

        buildCheckboxes();
        updateStatus(false);
        document.getElementById('resposta').innerHTML =
            '<div class="alert alert-info">Selecione uma ação para visualizar os resultados.</div>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>