<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Índices & Performance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/dark-mode.css">
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-end mb-3">
            <button id="themeToggle" class="btn btn-outline-secondary">
                <i class="bi bi-moon-fill"></i> Dark Mode
            </button>
        </div>
        <h1 class="mb-4 text-center"><i class="bi bi-search"></i> Índices & Performance</h1>

        <!-- Tabela e colunas -->
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Tabela:</label>
                <select id="tabela" class="form-select">
                    <option value="vendas">vendas</option>
                    <option value="pedidos">pedidos</option>
                    <option value="produtos">produtos</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Colunas para índice:</label>
                <div id="colunasIndice"></div>
            </div>
        </div>

        <!-- Tipo de consulta -->
        <div class="mb-3">
            <label class="form-label">Tipo de consulta:</label>
            <select id="tipoConsulta" class="form-select">
                <option value="data">Por Data</option>
                <option value="cliente">Por Cliente</option>
                <option value="vendedor_data">Por Vendedor + Data</option>
            </select>
        </div>

        <!-- Status índice -->
        <div id="statusIndice" class="text-center mb-4"></div>

        <!-- Botões -->
        <div class="d-flex justify-content-center gap-3 mb-4" id="botoesAcoes">
            <button id="btnPopularTodas" onclick="popularTodas(250000)" class="btn btn-warning btn-lg">
                <i class="bi bi-download"></i> Popular Todas
            </button>
            <button onclick="executarFluxo('sem')" class="btn btn-danger btn-lg" id="btnSemIndice">
                <i class="bi bi-x-circle"></i> Sem Índice
            </button>
            <button onclick="executarFluxo('com')" class="btn btn-success btn-lg" id="btnComIndice">
                <i class="bi bi-plus-circle"></i> Com Índice
            </button>
            <button onclick="resetarGrafico()" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-repeat"></i> Resetar Gráfico
            </button>
        </div>

        <div id="loadingSpinner" class="text-center mb-3" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2">Populando banco de dados, aguarde...</p>
        </div>

        <div id="resposta" class="mt-4"></div>

        <!-- Gráfico -->
        <div class="mt-5">
            <h5><i class="bi bi-bar-chart-line"></i> Gráfico de Desempenho</h5>
            <canvas id="graficoDesempenho" height="100"></canvas>
            <div id="melhoriaTexto" class="mt-3 text-center"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script src="../assets/js/index-logic.js"></script>
    <script>
        // Script para alternância de tema
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        // Verifica a preferência salva no localStorage
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
            
            // Atualizar cores do gráfico para corresponder ao tema
            if (window.grafico) {
                window.grafico.data.datasets[0].backgroundColor = newTheme === 'light' 
                    ? ['#dc3545', '#28a745'] 
                    : ['#ff6b6b', '#51cf66'];
                window.grafico.options.plugins.datalabels.color = newTheme === 'light' ? '#fff' : '#f8f9fa';
                window.grafico.update();
            }
        });
    </script>
</body>
</html>