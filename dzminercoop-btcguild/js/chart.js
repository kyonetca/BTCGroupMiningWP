AmCharts.ready(function () {
    createHashRateChart(dz_hashRateData);
});

function createHashRateChart() {
    chart = new AmCharts.AmStockChart();
    chart.pathToImages = "//cdnjs.cloudflare.com/ajax/libs/amstockchart/2.11.3/images/";

    // As we have second data, we should set minPeriod to "ss"
    var categoryAxesSettings = new AmCharts.CategoryAxesSettings();
    categoryAxesSettings.minPeriod = "ss";
    chart.categoryAxesSettings = categoryAxesSettings;

    // DATASETS //////////////////////////////////////////
    var colors = ["#b0de09", "#0000cc", "#cc0000", "#999999"];
    var dataSets = [];
    for (var i = 0; i < dz_hashRateData.length; i++) {
    var dataSet = new AmCharts.DataSet();
    dataSet.title = "Worker " + (i + 1).toString();
    dataSet.color = colors[i % colors.length];
    dataSet.fieldMappings = [{
        fromField: "value",
        toField: "value"
    }];
    dataSet.dataProvider = dz_hashRateData[i];
    dataSet.categoryField = "date";
    dataSets.push(dataSet);
    }

    // set data sets to the chart
    chart.dataSets = dataSets;

    // PANELS ///////////////////////////////////////////
    // first stock panel
    var stockPanel1 = new AmCharts.StockPanel();
    stockPanel1.showCategoryAxis = false;
    stockPanel1.title = "Gh/s";
    stockPanel1.percentHeight = 70;

    // graph of first stock panel
    var graph1 = new AmCharts.StockGraph();
    graph1.valueField = "value";
    graph1.comparable = false;
    graph1.type = "smoothedLine";
    graph1.lineThickness = 2;
    graph1.bullet = "round";
    graph1.bulletBorderColor = "#FFFFFF";
    graph1.bulletBorderAlpha = 1;
    graph1.bulletBorderThickness = 3;
    stockPanel1.addStockGraph(graph1);

    // create stock legend
    var stockLegend1 = new AmCharts.StockLegend();
    stockPanel1.stockLegend = stockLegend1;

    // set panels to the chart
    chart.panels = [stockPanel1];


    // OTHER SETTINGS ////////////////////////////////////
    var scrollbarSettings = new AmCharts.ChartScrollbarSettings();
    scrollbarSettings.graph = graph1;
    scrollbarSettings.updateOnReleaseOnly = true;
    scrollbarSettings.usePeriod = "10mm"; // this will improve performance
    scrollbarSettings.position = "top";
    chart.chartScrollbarSettings = scrollbarSettings;

    var cursorSettings = new AmCharts.ChartCursorSettings();
    cursorSettings.valueBalloonsEnabled = true;
    chart.chartCursorSettings = cursorSettings;

    // PERIOD SELECTOR ///////////////////////////////////
    var periodSelector = new AmCharts.PeriodSelector();
    periodSelector.position = "top";
    periodSelector.periods = [{
        period: "hh",
        count: 1,
        selected: true,
        label: "1 hour"
    }, {
        period: "DD",
        count: 1,
        label: "1 day"
    }, {
        period: "DD",
        count: 7,
        label: "1 week"
    }, {
        period: "MM",
        count: 1,
        label: "1 month"
    }, {
        period: "MAX",
        label: "MAX"
    }];
    chart.periodSelector = periodSelector;

    var panelsSettings = new AmCharts.PanelsSettings();
    panelsSettings.usePrefixes = true;
    chart.panelsSettings = panelsSettings;

    // DATA SET SELECTOR
    if (dz_hashRateData.length > 1) {
        var dataSetSelector = new AmCharts.DataSetSelector();
        dataSetSelector.position = "bottom";
        chart.dataSetSelector = dataSetSelector;
    }

    chart.write('hashratechartdiv');
}