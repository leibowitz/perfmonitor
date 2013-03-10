
function loadPieChart(data, div_id)
{
var formatCount = d3.format(",.0f");
var sourceData = data.map(function(d){return d.val;}),
    width = 600,
    height = 200,
    radius = 50,
    labelRadius = 60;

var vis = d3.select(div_id).append('svg')
    .data([data])
    .attr('width', width)
    .attr('height', height)
    // create a group to center pie chart
    .append('g')
    .attr('transform', 'translate(' + width/2 + ',' + height/2 + ')');

// Use the pie layout helper to create the pie slices
var pie = d3.layout.pie();

// Color helper for the fill colors
var color = d3.scale.category10();

// Arc is used to generate the path shape with the slices
var arc = d3.svg.arc()
    .innerRadius(0)
    .outerRadius(radius);

// Create the slices group to hold the slice shape and label
var slices = vis
    .selectAll('g.slice-group')
    // wrap the data with the pie which calculates the start and end angles
    .data(pie.value(function(d) { return d.val }))
    .enter()
      .append('g')
      .attr('class', 'slice-group');

// The slices
slices
    .append('path')
    .attr('class', 'slice')
    .attr('d', arc) // The shape is created by the arc and pie helpers
    .attr('fill', function(d, i) { return color(i); });

// The labels
var total = d3.sum(sourceData);

slices
    .append('text')
    // d.data is the original datum remember the data is wrapped in the pie helper
    .text(function(d) { return d.data.name+': '+formatCount(d.data.val)+'ms'+' (' + Math.round(d.value*100/total) + '%)'; })
    // Move the labels to the outside
    .each(function(d) {
        // Get the center of the slice and then move the label out
        var center = arc.centroid(d), // gives you the center point of the slice
            x = center[0],
            y = center[1],
            h = Math.sqrt(x*x + y*y),
            lx = x/h * labelRadius,
            ly = y/h * labelRadius;
        
        d3.select(this)
            .attr('y', ly)
            .attr('x', lx)
            .style('text-anchor', ((d.endAngle - d.startAngle)*0.5 + d.startAngle > Math.PI) ? 'end' : 'start');
    })
    // For demenstration, the labels without enough room will be gray
    .style('fill', function(d, index) {
       var bb = this.getBBox(), // A rectangle of the text
           p = [
             {x: bb.x, y: bb.y},
             {x: bb.x+bb.width, y: bb.y},
             {x: bb.x+bb.width, y: bb.y+bb.height},
             {x: bb.x, y: bb.y + bb.height}
           ];
        
        // Determine any of the corners are outside the slice angles
        var isOutside = false;
        for(var i=0; i<4; ++i) {
            
            // The angle from the center to the corner
            var a = (Math.atan2(p[i].y, p[i].x) + Math.PI*2.5) % (Math.PI*2);

            // Debugging display
            /*
            var line = this.ownerDocument.createElementNS("http://www.w3.org/2000/svg", "line");;
            line.setAttributeNS(null, 'x1', 0);
            line.setAttributeNS(null, 'y1', 0);
            line.setAttributeNS(null, 'x2', 0);
            line.setAttributeNS(null, 'y2', 0-width);
            line.setAttributeNS(null, 'transform', 'rotate('+(a*180/Math.PI)+')')
            line.setAttributeNS(null, 'style', 'opacity: 0.85; stroke: '+d3.rgb(color(index)).darker()+';stroke-width:1,');
            $(this).parent().append(line);
                
            var dot = this.ownerDocument.createElementNS("http://www.w3.org/2000/svg", "circle");;
            dot.setAttributeNS(null, 'cx', p[i].x);
            dot.setAttributeNS(null, 'cy', p[i].y);
            dot.setAttributeNS(null, 'r', 2);
            dot.setAttributeNS(null, 'fill', 'red');
            $(this).parent().append(dot);
            */
            // End debuggering display
            
            if(a < d.startAngle || a > d.endAngle) {
              isOutside = true;
            }
        }
        
        return isOutside ? '#999' : '#000';
    });

}

