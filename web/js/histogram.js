

     var sampsize = 0;
     var maxval = 0, minval = 10000;

    sampsize = data.length;

     maxval  = Math.max.apply(null, data);
     minval = Math.min.apply(null, data);

    // # of bins = k = (2*N)^(1/3) 

    // width of bin   = (max - min)/k
    var n_of_bins = Math.floor( Math.pow( 2*sampsize , 1/3 ) ) + 1;
    var stepsize  = (maxval - minval) / n_of_bins ;
    
    document.write('<p>Bins: ' + n_of_bins + '</p>');
    document.write('<p>Step Size: ' + stepsize + '</p>');
    document.write('<p>Min: ' + minval + '  ,  Max: ' + maxval + '</p>');

    var p = 0,
        w = 400,
        h = 500;

    var vis = d3.select("#histogram").append("svg:svg")
       .attr("width", w)
       .attr("height", h)
     .append("svg:g")
       .attr("transform", "translate(.5)");


    // -------------------------------------------------------------------
    // Add first data series
    // -------------------------------------------------------------------
    var histogram = d3.layout.histogram().range([minval, maxval]).bins(n_of_bins).frequency("density") 
        (data);
    var x = d3.scale.ordinal()
       .domain([minval, maxval])
       .rangeRoundBands([0, w-p]);

    var y = d3.scale.linear()
       .domain([0, d3.max(histogram, function(d) { return d.y; })])
       .range([0, (h-100)/3]);

    var margin = 50;

    vis.selectAll("rect")
     .data(histogram)
    .enter().append("svg:rect")
       .attr("width", "10px")
       .attr("x", function(d) { return d.x/Math.max(d.dx,1) * margin; })
       .attr("y", function(d) { return 100 - d.y * 10; })
       .attr("height", function(d) { return d.y * 10; })

vis.selectAll("text")
     .data(histogram)
      .enter().append("text")
       .attr("x", function(d) { return d.x/Math.max(d.dx,1) * margin + 10; })
       .attr("y", function(d) { return 95 - d.y * 10; })
     .attr("text-anchor", "end") // text-align: right
     .text(function(d) { return d.length ? d.length : ''; });


vis.selectAll("textlabel")
     .data(histogram)
      .enter().append("text")
       .attr("x", function(d) { return d.x/Math.max(d.dx,1) * margin + 10; })
       .attr("y", function(d) { return 120; })
     .attr("text-anchor", "end") 
     .text(function(d) { return Math.round(d.x) +'-'+ Math.round(d.x + d.dx); });

