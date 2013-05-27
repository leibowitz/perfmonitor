


function getPercentileRange(values, from, to)
{
    values.sort(d3.ascending);

    var pvalue1 = d3.quantile(values, from);
    var pvalue2 = d3.quantile(values, to);
    var idx1 = d3.bisect(values, pvalue1);
    var idx2 = d3.bisect(values, pvalue2);

    return values.slice(idx1, idx2);
}

function loadHistogramSimple(values, div_id, min, max)
{

// A formatter for counts.
var formatCount = d3.format(",.0f");

var margin = {top: 25, right: 0, bottom: 20, left: 50},
    width = 560 - margin.left - margin.right,
    height = 200 - margin.top - margin.bottom;


var mean = d3.mean(values);
var median = d3.median(values);

if(!min)
{
    min = d3.min(values);
}

if(!max)
{
    max = d3.max(values);
    if(min == max)
    {
        max = min+1;
    }
}

var color = d3.scale.category10();

var x = d3.scale.linear()
    //.domain(data.map(function(d){return d.x;}))
    //.rangeRoundBands([0, (width-margin.left-margin.right)]);
    .domain([min, max])
    .rangeRound([0, (width-margin.left-margin.right)])
    .nice();

var data = d3.layout.histogram()
    .bins(x.ticks(24))
    (values);

var maxY = d3.max(data, function(d) { return d.y; });
var y = d3.scale.linear()
    .domain([0, maxY])
    .range([height, 0]);

var xAxis = d3.svg.axis()
    .scale(x)
    .orient("bottom");

var yAxis = d3.svg.axis()
    .scale(y)
    .orient("left");

var svg = d3.select(div_id).append("svg")
    .attr("width", width+margin.left+margin.right)
    .attr("height", height+margin.top+margin.bottom)
  .append("g")
    .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

var bar = svg.selectAll(".bar")
    .data(data)
  .enter().append("g")
    .attr("class", "bar")
    .attr("transform", function(d) { return "translate(" + x(d.x) + "," + y(d.y) + ")"; });


bar_width = x(data[0].x+data[0].dx) - x(data[0].x);
bar.append("rect")
    //.attr("width", x.rangeBand())
    .attr("x", 1)
    .attr("width", bar_width-1)
    .attr("style", "fill:"+color(0))
    .attr("height", function(d) { return height-y(d.y)+1; });


bar.append("text")
    .attr("dy", ".75em")
    .attr("y", -15)
    //.attr("x", x.rangeBand()/2)
    .attr("x", bar_width/2)
    .attr("text-anchor", "middle")
    .text(function(d) { return formatCount(d.y); });

// show ms values or s
ms = max < 1 ? true:false;
svg.append("g")
    .attr("class", "x axis")
    .attr("transform", "translate("+0+"," + (height) + ")")
    .call(xAxis.tickFormat(function(d, i){return ms?d*1000:d;}));
    //d3.format(',.3f')));

svg.append("g")
    .attr("class", "y axis")
    .call(yAxis.ticks(5));

// Draw median, mean and stdev
stdev = getStandardDeviation(values);

draw_mark(svg, x, height, median, 'orange');
draw_mark(svg, x, height, mean, 'brown');
draw_mark(svg, x, height, mean+stdev, 'blue');

// Show min, max, stdev

d3.select(div_id).append("div")
.text("min: "+min+", max: "+max)
.append("div")
.text("stdev: "+stdev)
;

// Plot line of histogram data
var line = d3.svg.line()
    .interpolate("monotone")
    .x(function(d,i) { return x(d.x); })
    .y(function(d) { return y(d.y)+0.5; });


svg.append("svg:g")
    .append("svg:path")
    .attr("transform", "translate("+bar_width/2+",0)")
    .attr("d", line(data));


// Get random values
/*
var avalues = d3.range(1000).map(d3.random.normal(mean, stdev));

var data = d3.layout.histogram()
    .bins(x.ticks(24))
    (avalues);
*/
/*
var maxY = d3.max(data, function(d) { return d.y; });
var y = d3.scale.linear()
    .domain([0, maxY])
    .range([height, 0]);
*/

/*
var line = d3.svg.line()
    .interpolate("basis")
    .x(function(d) { return x(d.x); })
    .y(function(d) { return y(d.y); });

var cdata = d3.layout.histogram()
    .bins(x.ticks(24))
    (values);
var curve = svg.selectAll(".curve")
    .data([1])
  .enter()
    .append("path")
    .attr("class", "line")
    .attr("d", function(d) { return line(avalues); })
    .style("stroke", 'black');
*/

// Try to get approximation of the curve
//var avalues = d3.range(1000).map(d3.random.normal(mean, stdev));
//min = 0.4;
//max = 1.6;
var values = d3.range(0, 1, 1/50);
//console.log(values);

var y = d3.scale.linear()
    .domain([0, 3])
    .range([height, 0]);

//console.log(values.map(function(v){ return pdf(v, stdev, mean); }));

var line = d3.svg.line()
    .interpolate("monotone")
    .x(function(d,i) { return x(d); })
    .y(function(d) { return y(pdf(d, stdev, mean)); });

svg.append("svg:g")
    .append("svg:path")
    .attr("d", line(values));

}

function pdf(x, stdev, mean)
{
    if(x == 0)
        return 0;
        
    var v = 
    (1 / ( x * stdev * Math.sqrt( 2 * Math.PI ) ) )
        * Math.exp( 
            /*
            (- 1 / (2 * Math.pow(stdev, 2) ) )  
            * Math.pow( Math.log(x) - mean, 2 )
            */
            - ( Math.pow( Math.log(x) - mean, 2 ) / ( 2 * Math.pow(stdev,2) )  )
          );

    return v;
}

function draw_mark(svg, x, height, value, color)
{
    svg.append("line")
    .attr("style", "stroke:"+color)
    .attr("x1", x(value))
    .attr("y1", 0)
    .attr("x2", x(value))
    .attr("y2", height);
}

function getStandardDeviation(values)
{
    mean = d3.mean(values);
    dv = values.map(function(d){return Math.pow(d-mean, 2)});
    // divide by n-1 to get a better approximation
    return Math.sqrt(d3.sum(dv) / (values.length-1));
}

