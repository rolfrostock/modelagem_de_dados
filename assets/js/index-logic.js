document.addEventListener('DOMContentLoaded', () => {
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
            vendedor_data: ['nome_produto']
        }
    };

    let tabela = 'vendas';
    let tipoConsulta = 'data';
    let tempos = { sem: null, com: null };
    let grafico;

    function buildCheckboxes() {
        const div = document.getElementById('colunasIndice');
        div.innerHTML = '';

        colsByTable[tabela].forEach(col => {
            div.innerHTML += `
                <div class="form-check d-inline-block me-3" id="wrapper_${col}">
                    <input type="checkbox" class="form-check-input" id="col_${col}" value="${col}"
                        ${tabela === 'vendas' && col === 'data_venda' && tipoConsulta === 'data' ? 'checked' : ''}>
                    <label class="form-check-label" for="col_${col}">${col}</label>
                </div>`;
        });

        atualizarCheckboxesPorConsulta();
    }

    function atualizarCheckboxesPorConsulta() {
        const validCols = validColsByConsulta[tabela][tipoConsulta] || [];

        colsByTable[tabela].forEach(col => {
            const chk = document.getElementById(`col_${col}`);
            const wrapper = document.getElementById(`wrapper_${col}`);

            if (validCols.includes(col)) {
                chk.disabled = false;
                if (tabela === 'vendas' && col === 'data_venda' && tipoConsulta === 'data') {
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

    async function executarFluxo(tipo) {
        const tabela = document.getElementById('tabela').value;
        const tipoConsulta = document.getElementById('tipoConsulta').value;

        let colunasIndice = Array.from(document.querySelectorAll('#colunasIndice input:checked'))
            .map(el => el.value)
            .join(',');

        try {
            const response = await fetch('processa_acao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `acao=executar&table=${encodeURIComponent(tabela)}&tipoConsulta=${encodeURIComponent(tipoConsulta)}&colunasIndice=${encodeURIComponent(colunasIndice)}&ignorarIndice=${tipo === 'sem'}`
            });

            const data = await response.json();
            console.log('Resposta do servidor:', data);

            if (data.error) {
                if (data.error.includes("doesn't exist") && tipo === 'sem') {
                    atualizarResultados(data);
                } else {
                    alert(data.error);
                }
            } else {
                atualizarResultados(data);
            }
        } catch (error) {
            console.error('Erro na requisição:', error);
            alert(`Erro na execução: ${error.message || 'Erro desconhecido'}`);
        }
    }

    async function executarSemIndiceInicial() {
        const tabelaAtual = document.getElementById('tabela').value;
        const tipoConsultaAtual = document.getElementById('tipoConsulta').value;
        const colunas = getColunas();

        try {
            const response = await fetch('processa_acao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `acao=executar&table=${encodeURIComponent(tabelaAtual)}&tipoConsulta=${encodeURIComponent(tipoConsultaAtual)}&colunasIndice=${encodeURIComponent(colunas)}&ignorarIndice=true`
            });

            const data = await response.json();
            console.log('[DEBUG] Execução inicial SEM índice:', data);

            if (data.error) {
                console.warn('[AVISO] Erro esperado ignorado:', data.error);
            } else {
                document.getElementById('resposta').innerHTML = gerarTabelaResultados(data);
                tempos.sem = data.tempo_medio;
                updateStatus(false);
                if (!grafico) initChart(); else updateChart();
            }
        } catch (err) {
            console.error('[ERRO] ao executar sem índice inicialmente:', err);
        }
    }

    function atualizarResultados(data) {
        const tabelaResultados = document.getElementById('tabelaResultados').getElementsByTagName('tbody')[0];
        tabelaResultados.innerHTML = '';
        if (data.explain && Array.isArray(data.explain)) {
            data.explain.forEach(row => {
                const tr = tabelaResultados.insertRow();
                Object.values(row).forEach(value => {
                    const td = tr.insertCell();
                    td.textContent = value !== null ? value : '';
                });
            });
        }

        document.getElementById('totalLinhas').textContent = `Total de linhas: ${data.total || 0}`;
        document.getElementById('tempoMedio').textContent = `Tempo médio: ${data.tempo_medio || 0} ms`;
    }

    function gerarTabelaResultados(data) {
        console.log('[DEBUG] Dados EXPLAIN:', data.explain);

        let html = `<div class="card mb-4"><div class="card-body">
            <h5 class="card-title"><i class="bi bi-graph-up"></i> Resultado</h5>
            <p><strong>Tempo médio:</strong> ${data.tempo_medio} ms</p>
        </div></div>`;

        html += `<h5><i class="bi bi-search"></i> Plano de Execução (EXPLAIN)</h5>
            <table class="table table-bordered"><thead class="table-secondary"><tr>
                <th>ID</th><th>Type</th><th>Table</th><th>Key</th><th>Rows</th><th>Extra</th>
            </tr></thead><tbody>`;

        data.explain.forEach(row => {
            html += `<tr>
                <td>${row.id || ''}</td><td>${row.type || ''}</td><td>${row.table || ''}</td>
                <td>${row.key || ''}</td><td>${row.rows || ''}</td><td>${row.Extra || ''}</td>
            </tr>`;
        });
        html += `</tbody></table>`;
        return html;
    }

    function resetChart() {
        tempos = { sem: null, com: null };
        updateStatus(false);
        if (grafico) {
            grafico.data.datasets[0].data = [0, 0];
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
                labels: ['Sem Índice', 'Com Índice'],
                datasets: [{
                    label: 'Tempo (ms)',
                    data: [tempos.sem || 0, tempos.com || 0],
                    backgroundColor: isDarkMode 
                        ? ['#ff6b6b', '#51cf66'] 
                        : ['#dc3545', '#28a745']
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
        grafico.data.datasets[0].data = [tempos.sem || 0, tempos.com || 0];
        grafico.data.datasets[0].backgroundColor = isDarkMode 
            ? ['#ff6b6b', '#51cf66'] 
            : ['#dc3545', '#28a745'];
        grafico.options.plugins.datalabels.color = isDarkMode ? '#f8f9fa' : '#fff';
        grafico.update();
        updateImprovement();
    }

    function updateImprovement() {
        if (tempos.sem && tempos.com) {
            const pct = (((tempos.sem - tempos.com) / tempos.sem) * 100).toFixed(2);
            document.getElementById('melhoriaTexto').innerHTML =
                `<strong>Melhoria com índice:</strong> ${pct}% mais rápido`;
        } else {
            document.getElementById('melhoriaTexto').innerHTML = '';
        }
    }

    document.getElementById('tabela').addEventListener('change', e => {
        tabela = e.target.value;
        buildCheckboxes();
    });

    document.getElementById('tipoConsulta').addEventListener('change', e => {
        tipoConsulta = e.target.value;
        atualizarCheckboxesPorConsulta();
    });

    buildCheckboxes();
    updateStatus(false);
    executarSemIndiceInicial();
});