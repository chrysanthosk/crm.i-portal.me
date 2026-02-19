@extends('layouts.app')

@section('title', 'BI Reports')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0">Business Intelligence Dashboard</h1>
                <div class="text-muted small">YoY, YTD, Expenses, P&amp;L, Drill-down, Sales and LTV.</div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

    <div class="card">
        <div class="card-header p-2">
            <ul class="nav nav-pills" id="biTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    {{-- Bootstrap 4 uses data-toggle, not data-bs-toggle --}}
                    <a class="nav-link active" id="tab-yoy-link" data-toggle="tab" href="#tabYoY" role="tab">YoY Comparison</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tab-ytd-link" data-toggle="tab" href="#tabYTD" role="tab">YTD Dashboard</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tab-exp-link" data-toggle="tab" href="#tabExpenseCat" role="tab">Expense Analysis</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tab-pl-link" data-toggle="tab" href="#tabPL" role="tab">P&amp;L Statement</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tab-drill-link" data-toggle="tab" href="#tabDrill" role="tab">Drill-Down</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tab-sales-link" data-toggle="tab" href="#tabSalesCat" role="tab">Sales</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="tab-cltv-link" data-toggle="tab" href="#tabCLTV" role="tab">Customer LTV</a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">

                {{-- 1) YoY --}}
                <div class="tab-pane fade show active" id="tabYoY" role="tabpanel">
                    <div class="mb-3">
                        <canvas id="chartYoY" height="200"></canvas>
                    </div>

                    <div class="alert alert-warning small mb-3" id="yoyWarn" style="display:none;"></div>

                    <h5 class="mt-4">Month-Over-Month Comparison</h5>
                    <div class="table-responsive">
                        <table id="tblYoYCompare" class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th id="colThisYear">This Year</th>
                                    <th id="colLastYear">Last Year</th>
                                    <th>% Change</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- 2) YTD --}}
                <div class="tab-pane fade" id="tabYTD" role="tabpanel">
                    <canvas id="chartYTD" height="200"></canvas>
                    <div class="alert alert-info small mt-3 mb-0" id="ytdWarn" style="display:none;"></div>
                </div>

                {{-- 3) Expense --}}
                <div class="tab-pane fade" id="tabExpenseCat" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <canvas id="chartExpenseCat" height="200"></canvas>
                        </div>
                        <div class="col-md-6 mb-3">
                            <canvas id="chartTopVendors" height="200"></canvas>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0" id="expenseWarn" style="display:none;"></div>
                </div>

                {{-- 4) P&L --}}
                <div class="tab-pane fade" id="tabPL" role="tabpanel">
                    <canvas id="chartPL" height="200"></canvas>
                    <div class="alert alert-info small mt-3 mb-0" id="plWarn" style="display:none;"></div>
                </div>

                {{-- 5) Drill --}}
                <div class="tab-pane fade" id="tabDrill" role="tabpanel">
                    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                        <div>
                            <label class="form-label mb-1">From</label>
                            <input type="date" id="drillStart" class="form-control form-control-sm" value="{{ now()->startOfMonth()->toDateString() }}">
                        </div>
                        <div>
                            <label class="form-label mb-1">To</label>
                            <input type="date" id="drillEnd" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                        </div>
                        <div>
                            <button id="btnDrill" class="btn btn-sm btn-primary">Load</button>
                        </div>
                    </div>

                    <div id="drillTables">
                        <h5>Sales</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="tblSales">
                                <thead><tr><th>Date</th><th class="text-right">Amount</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <h5 class="mt-4">Expenses</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="tblExpenses">
                                <thead><tr><th>Date</th><th>Name</th><th class="text-right">Amount</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="alert alert-info small mt-3 mb-0" id="drillWarn" style="display:none;"></div>
                    </div>
                </div>

                {{-- 6) Sales --}}
                <div class="tab-pane fade" id="tabSalesCat" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <canvas id="chartSalesByCat" height="200"></canvas>
                        </div>
                        <div class="col-md-6 mb-3">
                            <canvas id="chartTopProducts" height="200"></canvas>
                        </div>
                    </div>
                    <div class="alert alert-warning small mb-0" id="salesWarn" style="display:none;"></div>
                </div>

                {{-- 7) LTV --}}
                <div class="tab-pane fade" id="tabCLTV" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <canvas id="chartCustomerSegments" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Segment</th>
                                            <th class="text-right">Avg. LTV (€)</th>
                                            <th class="text-right">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tblCLTV"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning small mb-0" id="cltvWarn" style="display:none;"></div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    (function () {
        const endpoint = "{{ route('reports.data') }}";

        async function fetchJSON(params) {
            const url = new URL(endpoint, window.location.origin);
            Object.keys(params).forEach(k => url.searchParams.set(k, params[k]));
            const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' }});
            const j = await r.json();
            if (!r.ok || (j && j.ok === false)) throw new Error(j?.error || j?.message || 'Request failed');
            return j;
        }

        function showWarn(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = msg ? '' : 'none';
            el.textContent = msg || '';
        }

        function fmt(n) {
            const v = Number(n || 0);
            return v.toFixed(2);
        }

        let charts = {}; // keep refs

        function renderLineChart(canvasId, labels, datasets, options = {}) {
            const el = document.getElementById(canvasId);
            if (!el) return;
            if (charts[canvasId]) charts[canvasId].destroy();
            charts[canvasId] = new Chart(el, {
                type: 'line',
                data: { labels, datasets },
                options: Object.assign({ responsive: true }, options),
            });
        }

        function renderBarChart(canvasId, labels, datasets, options = {}) {
            const el = document.getElementById(canvasId);
            if (!el) return;
            if (charts[canvasId]) charts[canvasId].destroy();
            charts[canvasId] = new Chart(el, {
                type: 'bar',
                data: { labels, datasets },
                options: Object.assign({ responsive: true }, options),
            });
        }

        function renderDoughnut(canvasId, labels, data, options = {}) {
            const el = document.getElementById(canvasId);
            if (!el) return;
            if (charts[canvasId]) charts[canvasId].destroy();
            charts[canvasId] = new Chart(el, {
                type: 'doughnut',
                data: { labels, datasets: [{ data }] },
                options: Object.assign({ responsive: true }, options),
            });
        }

        function renderPie(canvasId, labels, data, options = {}) {
            const el = document.getElementById(canvasId);
            if (!el) return;
            if (charts[canvasId]) charts[canvasId].destroy();
            charts[canvasId] = new Chart(el, {
                type: 'pie',
                data: { labels, datasets: [{ data }] },
                options: Object.assign({ responsive: true }, options),
            });
        }

        // 1) YoY
        fetchJSON({ report: 'yoy' }).then(d => {
            showWarn('yoyWarn', '');
            renderLineChart('chartYoY', d.labels, d.datasets);
        }).catch(e => showWarn('yoyWarn', e.message));

        // 1b) YoY Table
        fetchJSON({ report: 'yoy_table' }).then(d => {
            document.getElementById('colThisYear').textContent = d.yearCurrent;
            document.getElementById('colLastYear').textContent = d.yearLast;
            const tbody = document.querySelector('#tblYoYCompare tbody');
            tbody.innerHTML = '';
            d.rows.forEach(r => {
                const last = parseFloat(r.last);
                const cur = parseFloat(r.cur);
                const pct = last === 0 ? '—' : (((cur - last) / last) * 100).toFixed(1) + '%';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${r.month}</td>
                    <td class="text-right">${fmt(cur)}</td>
                    <td class="text-right">${fmt(last)}</td>
                    <td class="text-right">${pct}</td>
                `;
                tbody.appendChild(tr);
            });
        }).catch(e => console.error(e));

        // 2) YTD
        fetchJSON({ report: 'ytd' }).then(d => {
            showWarn('ytdWarn', d.warning || '');
            renderBarChart('chartYTD', d.labels, [
                { label: 'Income', data: d.income },
                { label: 'Expenses', data: d.expenses }
            ]);
        }).catch(e => showWarn('ytdWarn', e.message));

        // 3) Expense Category + Top Vendors
        fetchJSON({ report: 'expense_cat' }).then(d => {
            showWarn('expenseWarn', '');
            renderDoughnut('chartExpenseCat', d.labels, d.data);
        }).catch(e => showWarn('expenseWarn', e.message));

        fetchJSON({ report: 'top_vendors' }).then(d => {
            showWarn('expenseWarn', '');
            renderBarChart('chartTopVendors', d.labels, [
                { label: 'Expenses', data: d.data }
            ]);
        }).catch(e => showWarn('expenseWarn', e.message));

        // 4) P&L
        fetchJSON({ report: 'pl' }).then(d => {
            showWarn('plWarn', d.warning || '');
            renderLineChart('chartPL', d.labels, [
                { label: 'Income', data: d.income },
                { label: 'Expenses', data: d.expenses },
                { label: 'Profit', data: d.profit },
            ]);
        }).catch(e => showWarn('plWarn', e.message));

        // 5) Drill-down
        async function loadDrill() {
            showWarn('drillWarn', '');
            const s = document.getElementById('drillStart').value;
            const e = document.getElementById('drillEnd').value;

            try {
                const d = await fetchJSON({ report: 'drill', start: s, end: e });

                const salesBody = document.querySelector('#tblSales tbody');
                salesBody.innerHTML = '';
                d.sales.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${r.date}</td><td class="text-right">${fmt(r.amount)}</td>`;
                    salesBody.appendChild(tr);
                });

                const expBody = document.querySelector('#tblExpenses tbody');
                expBody.innerHTML = '';
                d.expenses.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${r.date}</td><td>${r.name}</td><td class="text-right">${fmt(r.amount)}</td>`;
                    expBody.appendChild(tr);
                });

                showWarn('drillWarn', d.warning || '');
            } catch (err) {
                showWarn('drillWarn', err.message);
            }
        }

        document.getElementById('btnDrill').addEventListener('click', loadDrill);
        loadDrill();

        // 6) Sales by Category + Top Products
        fetchJSON({ report: 'sales_category' }).then(d => {
            showWarn('salesWarn', '');
            renderPie('chartSalesByCat', d.categories, d.totals);
        }).catch(e => showWarn('salesWarn', e.message));

        fetchJSON({ report: 'top_products' }).then(d => {
            showWarn('salesWarn', '');
            renderBarChart('chartTopProducts', d.products, [
                { label: 'Revenue', data: d.revenue }
            ]);
        }).catch(e => showWarn('salesWarn', e.message));

        // 7) Customer LTV (segments chart + table)
        fetchJSON({ report: 'customer_ltv' }).then(d => {
            showWarn('cltvWarn', '');
            renderDoughnut('chartCustomerSegments', d.segments, d.counts);

            const tbody = document.getElementById('tblCLTV');
            tbody.innerHTML = '';
            d.segments.forEach((seg, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${seg}</td>
                    <td class="text-right">${fmt(d.avg_ltv[i] || 0)}</td>
                    <td class="text-right">${parseInt(d.counts[i] || 0)}</td>
                `;
                tbody.appendChild(tr);
            });
        }).catch(e => showWarn('cltvWarn', e.message));

    })();
    </script>
@endpush
