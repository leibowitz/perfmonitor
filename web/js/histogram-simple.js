function loadHistogramSimple(values, div_id)
{

// A formatter for counts.
var formatCount = d3.format(",.0f");

var margin = {top: 0, right: 0, bottom: 20, left: 50},
    width = 560 - margin.left - margin.right,
    height = 200 - margin.top - margin.bottom;

var min = d3.min(values);
var max = d3.max(values);
if(min == max)
{
    max = min+1;
}

// get 90 % of the data, sorted (remove extremes)
values.sort(d3.ascending);
var pvalue1 = d3.quantile(values, 0);
var pvalue2 = d3.quantile(values, .90);
var idx1 = d3.bisect(values, pvalue1);
var idx2 = d3.bisect(values, pvalue2);
values = values.slice(idx1, idx2);

var data = d3.layout.histogram()
    .bins(10)
    (values);

var x = d3.scale.ordinal()
    .domain(data.map(function(d){return d.x;}))
    .rangeRoundBands([0, (width-margin.left-margin.right)]);

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

bar.append("rect")
    .attr("width", x.rangeBand())
    .attr("height", function(d) { return height-y(d.y); });

bar.append("text")
    .attr("dy", ".75em")
    .attr("y", 6)
    .attr("x", x.rangeBand()/2)
    .attr("text-anchor", "middle")
    .text(function(d) { return formatCount(d.y); });

svg.append("g")
    .attr("class", "x axis")
    .attr("transform", "translate("+0+"," + (height) + ")")
    .call(xAxis.tickFormat(d3.format(',.3f')));

svg.append("g")
    .attr("class", "y axis")
    .call(yAxis);
}


