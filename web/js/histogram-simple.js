function loadHistogramSimple(values, div_id)
{

// A formatter for counts.
var formatCount = d3.format(",.0f");

var margin = {top: 10, right: 30, bottom: 30, left: 30},
    width = 560 - margin.left - margin.right,
    height = 200 - margin.top - margin.bottom;

var min = d3.min(values);
var max = d3.max(values);

if(min == max)
{
    min = Math.max(min-1, 0);
}

var x = d3.scale.linear()
    .domain([min, max])
    .range([0, width]);

// Generate a histogram using five uniformly-spaced bins.
var _bins = x.ticks(20);

var data = d3.layout.histogram()
    .bins(_bins)
    (values);

var maxY = d3.max(data, function(d) { return d.y; });
var minX = d3.min(data, function(d) { return d.x; });
var maxX = d3.max(data, function(d) { return d.x; });

var xdiff = Math.max((x(maxX)-x(minX))/_bins.length, 10);

var y = d3.scale.linear()
    .domain([0, maxY])
    .range([height, 0]);

var xAxis = d3.svg.axis()
    .scale(x)
    .orient("bottom");

var yAxis = d3.svg.axis()
    .scale(y)
    .orient("left");

var svg = d3.select("#"+div_id).append("svg")
    .attr("width", width + margin.left + margin.right)
    .attr("height", height + margin.top + margin.bottom)
  .append("g")
    .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

var bar = svg.selectAll(".bar")
    .data(data)
  .enter().append("g")
    .attr("class", "bar")
    .attr("transform", function(d) { return "translate(" + x(d.x) + "," + y(d.y) + ")"; });

bar.append("rect")
    .attr("width", function(d){ return xdiff})
    .attr("height", function(d) { return height - y(d.y); });

bar.append("text")
    .attr("dy", ".75em")
    .attr("y", 6)
    .attr("x", xdiff/2)
    .attr("text-anchor", "middle")
    .text(function(d) { return formatCount(d.y); });

svg.append("g")
    .attr("class", "x axis")
    .attr("transform", "translate(0," + height + ")")
    .call(xAxis.ticks(_bins.length/2, 1));

svg.append("g")
    .attr("class", "y axis")
    .call(yAxis.ticks(maxY, 1));
}


