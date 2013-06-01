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

    var height = 300;
    var width = 900;
    var margin_h = 30;
    var margin_w = 50;

    var min = +Infinity;
    var max = -Infinity;

    date_from = new Date(date_from);
    date_to = new Date(date_to);
    // Eventually add one day to the right
    date_to.setDate(date_to.getDate() + 1);
    
    var x = d3.time.scale()
        .domain([date_from, date_to])
        .range([margin_w, width-margin_w]);
    
    var values = d3.map(datas);
    // Create Date object for the whole data set
    var entries = values.entries();
    for(i in entries)
    {
        // Set js timestamp and date object 
        entries[i].time = entries[i].key * 1000;
        entries[i].date = new Date(entries[i].time);
        // Sort values
        entries[i].value.sort(d3.ascending);
        // Mean
        entries[i].mean = d3.mean(entries[i].value);
        // Median
        entries[i].median = d3.median(entries[i].value);
        // Set first and third quartile
        entries[i].quart1 = d3.quantile(entries[i].value, .25);
        entries[i].quart3 = d3.quantile(entries[i].value, .75);
        // Midhinge
        entries[i].midhinge = (entries[i].quart3 + entries[i].quart1)/2;
        // IQR
        entries[i].iqr = entries[i].quart3 - entries[i].quart1;
        // Lower and upper fences
        //entries[i].lower = entries[i].quart1 - 1.5*entries[i].iqr;
        //entries[i].upper = entries[i].quart3 + 1.5*entries[i].iqr;
        entries[i].lower = d3.quantile(entries[i].value, .09);
        entries[i].upper = d3.quantile(entries[i].value, .91);
        // trimean 20, 50 and 80
    }

    var dates = values.keys().sort(d3.ascending);

    for(time in datas)
    {
        m = d3.max(datas[time]);
        if(m > max)
            max = m;
        m = d3.min(datas[time]);
        if(m < min)
            min = m;
    }

    // Change domain to have margins above and below boxes
    min = min * 0.9;
    max = max * 1.1;

    var y = d3.scale.linear()
        .domain([max, min])
        .range([margin_h, height-margin_h]);

    // Get the lowest time
    var d1 = d3.min(dates);
    // Find width between two ticks
    var bar_width = (x(d1*1000+86400000)-x(d1*1000));

    var div = d3.select(div_id)
        .append("div");
    
    var svg = div
        .append("svg")
        .attr("width", width)
        .attr("height", height);
        
    var bar = svg.selectAll(".bar")
        .data(entries)
      .enter();
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
        .attr("transform", function(d, i){return "translate("+(x(d.time)+bar_width*.1)+","+0+")";})
        .text(function(d){ return d.key;})
        .append("rect")
        //.attr("x", function(d, i){return x(d.key*1000);})
        .attr("y", function(d, i){
        
            if(d.value.length == 1)
                return y(d.value[0]);
            return y(d.quart3);
            })
        .attr("width", bar_width*.8)
        .attr("height", function(d, i){ 
            if(d.value.length == 1)
                return 1;
            return y(d.quart1) - y(d.quart3)
        });


        // Bar up
        bar.append("g")
        .append('line')
        .attr("x1", function(d,i){ return x(d.time)+bar_width/2})
        //.attr("y1", function(d,i){ return y(d3.max(d.value))})
        .attr("y1", function(d,i){ return y(d.upper)})
        .attr("x2", function(d,i){ return x(d.time)+bar_width/2})
        .attr("y2", function(d,i){ return y(d.quart3)})
        .attr("stroke", "black");
       
        // -
        bar.append("g")
        .append('line')
        .attr("x1", function(d,i){ return x(d.time)+bar_width*.45})
        .attr("y1", function(d,i){ return y(d.upper)})
        .attr("x2", function(d,i){ return x(d.time)+bar_width*.55})
        .attr("y2", function(d,i){ return y(d.upper)})
        .attr("stroke", "black");

        // Bar down
        bar.append("g")
        .append('line')
        .attr("x1", function(d,i){ return x(d.time)+bar_width/2})
        .attr("y1", function(d,i){ return y(d.lower)})
        //.attr("y1", function(d,i){ return y(d3.min(d.value))})
        .attr("x2", function(d,i){ return x(d.time)+bar_width/2})
        .attr("y2", function(d,i){ return y(d.quart1)})
        .attr("stroke", "black");
        
        // -
        bar.append("g")
        .append('line')
        .attr("x1", function(d,i){ return x(d.time)+bar_width*.45})
        .attr("y1", function(d,i){ return y(d.lower)})
        .attr("x2", function(d,i){ return x(d.time)+bar_width*.55})
        .attr("y2", function(d,i){ return y(d.lower)})
        .attr("stroke", "black");

        // Mean
        bar.append("circle")
        .attr("cx", function(d,i){ return x(d.time)+bar_width/2})
        .attr("cy", function(d,i){ return y(d.mean)})
		.attr("r", 2)
        .attr("fill", "red")
        .append('svg:title')
        .text(function(d){return d.value;});

        bar.append("g")
        .append('line')
        .attr("x1", function(d,i){ return x(d.time)+bar_width*.45;})
        .attr("y1", function(d,i){ return y(d.mean)})
        .attr("x2", function(d,i){ return x(d.time)+bar_width*.55;})
        .attr("y2", function(d,i){ return y(d.mean)})
        .attr("stroke", "red");
        
        // Median 
        bar.append("g")
        .append('line')
        .attr("x1", function(d,i){ return x(d.time)+bar_width*.1;})
        .attr("y1", function(d,i){ return y(d.median)})
        .attr("x2", function(d,i){ return x(d.time)+bar_width*.9;})
        .attr("y2", function(d,i){ return y(d.median)})
        .attr("stroke", "blue");

    var xAxis = d3.svg.axis()
        .scale(x)
        //.orient("bottom")
        .ticks(d3.time.days,1)
        .tickFormat(d3.time.format("%d/%m"))
        .tickSize(6, 0);

    svg.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + (height-margin_h) + ")")
        .call(xAxis);

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

}


