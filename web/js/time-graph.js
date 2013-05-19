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

function showTimesGraph(values, div_id, date_from, date_to)
{
    var height = 200;
    var margin_h = 20;
    var margin_w = 50;

    var h=100;
    var w=20;
    
    date_from = new Date(date_from);
    date_to = new Date(date_to);

    values.sort(sort_by_time);
    values_key = values.map(function(d){return d.date;});
    values_val = values.map(function(d){return d.value;});

    var width = 600;
    
    var x = d3.time.scale()
        .domain([date_from, date_to])
        .range([margin_w, width-margin_w]);

    var y = d3.scale.linear()
        .domain([d3.max(values_val)+1, Math.max(d3.min(values_val)-2,0)])
        .range([margin_h, height-margin_h]);

    var div = d3.select(div_id)
        .append("div");
    
    var svg = div
        .append("svg")
        .attr("width", width)
        .attr("height", height)
      .append("g");
        

    var bar = svg.selectAll(".bar")
        .data(values)
      .enter();

    bar.append("circle")
        .attr("cx", function(d,i){ return x(d.date)})
        .attr("cy", function(d,i){ return y(d.value)})
		.attr("r", 5)
        .attr("fill", "red");

    var text = svg.selectAll(".text")
        .data(values)
        .enter();

    msformat = d3.format('.3f');
    text.append("text")
        .attr("x", function(d,i){ return x(d.date); })
        .attr("y", function(d,i){ return y(d.value)-5; })
        .text(function(d,i){ return msformat(d.value)+'s'; });

    bar.append("line")
        .attr("x1", function(d,i){ return x(d.date)})
        .attr("y1", function(d,i){ return y(d.value)})
        .attr("x2", function(d,i){ return values_key[i+1] ? x(values_key[i+1]) : x(d.date)})
        .attr("y2", function(d,i){ return values_val[i+1] ? y(values_val[i+1]) : y(d.value)})
        .attr("stroke", "red");

    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom")
        .ticks(d3.time.days, 1);

    svg.append("g")
        .attr("class", "x axis")
		.attr("opacity", 0.7)
        .attr("transform", "translate(0," + (height-20) + ")")
        .call(xAxis);

    var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left")
        .ticks(5);

    svg.append("g")
        .attr("class", "y axis")
        .attr("width", 1)
		.attr("opacity", 0.7)
        .attr("transform", "translate(50,0)")
        .call(yAxis);
    
}
