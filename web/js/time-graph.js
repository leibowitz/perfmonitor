function getValue(element)
{
    return element['value'];
}

function getNValuesAvg(values, idx, nb)
{
    vals = values.slice(idx, idx+nb).map(getValue);
    return {
        'date': values[idx]['date'],
        'value': d3.mean(vals)
    };
    
}

function getMovingAverages(values)
{
    var nb = 3;
    var results = new Array();
    var start = nb;
    for(var i=start;i<values.length+1;i++)
    {
        r = getNValuesAvg(values, i-start, nb);
        results.push(r);
    }
    return results;
}
function getData()
{
    return d3.range(33).map(d3.random.normal(10));
}

function getHistAvgValue(values)
{
    values.sort(d3.ascending);
    quart1 = d3.quantile(values, .25);
    quart3 = d3.quantile(values, .75);
    return (quart1+quart3)/2;
}

function sort_by_time(a, b) {
    return a.date < b.date ? -1 : a.date > b.date ? 1 : a.date >= b.date ? 0 : NaN;
};

function groupValuesByDate(values)
{
    var datas = {}, key;

    for(i in values)
    {
		key = Math.floor(values[i].date / 86400) * 86400;
        if(!datas[key])
        {
            datas[key] = [];
        }

        datas[key].push(values[i].value);
    }
    return datas;
}

function showBoxPlot(datas, div_id, date_from, date_to)
{
    // Define plot size and margins
    var height = 300;
    var width = 900;
    var margin_h = 30;
    var margin_w = 50;

    // Define the upper and lower whisker 
    var data_percentile = .09;
    
    // create Date objects for xAxis domain
    date_from = new Date(date_from);
    date_to = new Date(date_to);
    
    // Interval between from and to
    var domain = d3.time.days(d3.time.day.offset(date_from, -1), date_to);

    // setup rangedata keys using the timestamp of the dates
    var rangedata = {};
    for(k in domain)
    {
        rangedata[ domain[k].getTime() ] = {'date': domain[k]};
    }

    // Setup rangedata entries with all the computer values for 
    // plotting the boxes
    var values = d3.map(datas);

    // Create Date object for the whole data set
    var entries = values.entries();
    for(url in entries)
    {
        entries[url].value.sort(d3.ascending);

        var time = entries[url].key * 1000;
        var date = new Date(time);
        var key = time;
        // Set js timestamp and date object 
        rangedata[key].time = time;
        //rangedata[date].date = date;
        rangedata[key].url = url;
        // Sort values
        rangedata[key].value = entries[url].value;
        // Mean
        rangedata[key].mean = d3.mean(entries[url].value);
        // Median
        rangedata[key].median = d3.median(entries[url].value);
        // Set first and third quartile
        rangedata[key].quart1 = d3.quantile(entries[url].value, .25);
        rangedata[key].quart3 = d3.quantile(entries[url].value, .75);
        // Midhinge
        rangedata[key].midhinge = (rangedata[key].quart3 + rangedata[key].quart1)/2;
        // IQR
        rangedata[key].iqr = rangedata[key].quart3 - rangedata[key].quart1;
        // Lower and upper fences
        //rangedata[key].lower = entries[url].quart1 - 1.5*entries[url].iqr;
        //rangedata[key].upper = entries[url].quart3 + 1.5*entries[url].iqr;
        rangedata[key].lower = d3.quantile(entries[url].value, data_percentile);
        rangedata[key].upper = d3.quantile(entries[url].value, 1-data_percentile);
        // trimean 20, 50 and 80

        // Store a value that is the maximum
        rangedata[key].max = d3.max([rangedata[key].mean, rangedata[key].upper]);
        rangedata[key].min = d3.min([rangedata[key].mean, rangedata[key].lower]);
    }
    
    // Change y domain values to have margins above and below min/max
    var margin_ud = 0.1;
    // Find global min and max of all distributions
    var min = d3.min(d3.values(rangedata).map(function(data){ return data.min;})) * (1-margin_ud) || 1;
    var max = d3.max(d3.values(rangedata).map(function(data){ return data.max;})) * (1+margin_ud) || 10;

    // y Axis
    var y = d3.scale.linear()
        .domain([max, min])
        .range([margin_h, height-margin_h]);
    
    // x Axis
    var x = d3.scale.ordinal()
        .domain(domain)
        .rangeRoundBands([margin_w, width-margin_w]);
    
    // Margin to the left and right of the boxes (in % of total width)
    var bin_margin_val = .2;

    // Store variables for sizes used to draw boxes
    var bin_width = x.rangeBand();
    var halfbin_width = bin_width/2;

    var bin_margin = bin_margin_val*bin_width;
    var halfbin_margin = bin_margin/2;

    var bar_width = bin_width-bin_margin;
    var halfbar_width = bar_width/2;

    // Create graph div and svg elements
    var div = d3.select(div_id)
        .append("div");
    
    var svg = div
        .append("svg")
        .attr("width", width)
        .attr("height", height);

    // Get ticks every X days
    var tickDays = domain.length > 10 ? 2 : 1;
    var ticks = domain.filter(function(d, i){ return i%tickDays==0;});

    // Show x axis
    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom")
        .tickValues(ticks)
        //.ticks(d3.time.days, 2)
        .tickFormat(d3.time.format("%d/%m"))
        .tickSize(6, 0);

    svg.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + (height-margin_h) + ")")
        .call(xAxis);

    // Show y axis
    var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left")
        //.ticks(5)
        .tickSize(4, 0);

    svg.append("g")
        .attr("class", "y axis")
        .attr("transform", "translate("+margin_w+",0)")
        .call(yAxis);
  

    // Adding grid lines
    svg.append("g")         
        .attr("class", "grid")
        .attr("transform", "translate(0," + (margin_h) + ")")
        .call(xAxis
            .tickSize(height-(margin_h*2))
            .tickFormat("")
        );

    svg.append("g")         
        .attr("class", "grid")
        .attr("transform", "translate("+margin_w+",0)")
        .call(yAxis
            .orient("right")
            .tickSize(width-(margin_w*2))
            .tickFormat("")
        );

    // Start drawing the data 
    var bar = svg.selectAll(".bar")
        .data(d3.map(rangedata).values())
      .enter()
      ;
/*
    values.sort(sort_by_time);
    if(values.length > 3)
    {
        values = getMovingAverages(values);
    }
    values_key = values.map(function(d){return d.date;});
    values_val = values.map(function(d){return d.value;});
    
    bar.append("circle")
        .attr("cx", function(d,i){ return x(d.date)})
        .attr("cy", function(d,i){ return y(d.value)})
		.attr("r", 2)
        .attr("fill", "red")
        .append('svg:title')
        .text(function(d){return msformat(d.value)+'s';});

    bar.append("line")
        .attr("x1", function(d,i){ return x(d.date)})
        .attr("y1", function(d,i){ return y(d.value)})
        .attr("x2", function(d,i){ return values_key[i+1] ? x(values_key[i+1]) : x(d.date)})
        .attr("y2", function(d,i){ return values_val[i+1] ? y(values_val[i+1]) : y(d.value)})
        .attr("stroke", "green");
*/
        
        // Rectangle
        bar
        .append("g")
        
        .filter(function(d){ return d.value != null; })
        .append("rect")
        .attr("x", function(d, i){return x(d.date)+halfbin_margin;})
        .attr("y", function(d, i){
            if(!d.value)
                return 0;
            if(d.value.length == 1)
                return y(d.value[0]);
            return y(d.quart3);
            })
        .attr("width", bar_width)
        .attr("height", function(d, i){ 
            if(!d.value)
                return 0;
            if(d.value.length == 1)
                return 1;
            return y(d.quart1) - y(d.quart3)
        });


        // Bar up
        bar.append("g")
        .filter(function(d){ return d.value != null; })
        .append('line')
        .attr("x1", function(d,i){ return x(d.date)+halfbin_width})
        .attr("y1", function(d,i){ return y(d.upper)})
        .attr("x2", function(d,i){ return x(d.date)+halfbin_width})
        .attr("y2", function(d,i){ return y(d.quart3)})
        .attr("stroke", "black");
       
        // Upper whisker
        bar.append("g")
        .filter(function(d){ return d.value != null; })
        .append('line')
        .attr("x1", function(d,i){ return x(d.date)+halfbin_width-bar_width*.05;})
        .attr("y1", function(d,i){ return y(d.upper)})
        .attr("x2", function(d,i){ return x(d.date)+halfbin_width+bar_width*.05;})
        .attr("y2", function(d,i){ return y(d.upper)})
        .attr("stroke", "black");

        // Bar down
        bar.append("g")
        .filter(function(d){ return d.value != null; })
        .append('line')
        .attr("x1", function(d,i){ return x(d.date)+halfbin_width})
        .attr("y1", function(d,i){ return y(d.lower)})
        .attr("x2", function(d,i){ return x(d.date)+halfbin_width})
        .attr("y2", function(d,i){ return y(d.quart1)})
        .attr("stroke", "black");
        
        // Lower whisker
        bar.append("g")
        .filter(function(d){ return d.value != null; })
        .append('line')
        .attr("x1", function(d,i){ return x(d.date)+halfbin_width-bar_width*.05;})
        .attr("y1", function(d,i){ return y(d.lower)})
        .attr("x2", function(d,i){ return x(d.date)+halfbin_width+bar_width*.05;})
        .attr("y2", function(d,i){ return y(d.lower)})
        .attr("stroke", "black");

        // Mean Dot
        bar.append("circle")
        .filter(function(d){ return d.value != null; })
        .attr("cx", function(d,i){ return x(d.date)+halfbin_width})
        .attr("cy", function(d,i){ return y(d.mean)})
		.attr("r", 2)
        .attr("fill", "red")
        .append('svg:title')
        .text(function(d){return d.mean;});

        // Mean Line
        bar.append("g")
        .filter(function(d){ return d.value != null; })
        .append('line')
        .attr("x1", function(d,i){ return x(d.date)+halfbin_width-bar_width*.05;})
        .attr("y1", function(d,i){ return y(d.mean)})
        .attr("x2", function(d,i){ return x(d.date)+halfbin_width+bar_width*.05;})
        .attr("y2", function(d,i){ return y(d.mean)})
        .attr("stroke", "red");
       
        // Median 
        bar.append("g")
        .filter(function(d){ return d.value != null; })
        .append('line')
        .attr("x1", function(d,i){ return x(d.date)+halfbin_margin;})
        .attr("y1", function(d,i){ return y(d.median)})
        .attr("x2", function(d,i){ return x(d.date)+halfbin_margin+bar_width;})
        .attr("y2", function(d,i){ return y(d.median)})
        .attr("stroke", "blue");

}


