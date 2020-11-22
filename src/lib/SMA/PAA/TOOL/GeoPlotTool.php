<?php
namespace SMA\PAA\TOOL;

class GeoPlotTool
{
    private $elements;

    public function __construct()
    {
        $this->elements = [];
    }

    private function createEmptyElement(): array
    {
        $element = [];
        $element["type"] = "";
        $element["lat"] = 0.0;
        $element["lon"] = 0.0;
        $element["toLat"] = 0.0;
        $element["toLon"] = 0.0;
        $element["radius"] = 0.0;
        $element["strokeColor"] = "";
        $element["fillColor"] = "";
        $element["text"] = "";
        
        return $element;
    }

    public function addPoint(array $latLon, string $color)
    {
        $element = $this->createEmptyElement();

        $element["type"] = "point";
        $element["lat"] = $latLon["lat"];
        $element["lon"] = $latLon["lon"];
        $element["strokeColor"] = "black";
        $element["fillColor"] = $color;

        $this->elements[] = $element;
    }

    public function addLine(array $fromLatLon, array $toLatLon, string $color)
    {
        $element = $this->createEmptyElement();

        $element["type"] = "line";
        $element["lat"] = $fromLatLon["lat"];
        $element["lon"] = $fromLatLon["lon"];
        $element["toLat"] = $toLatLon["lat"];
        $element["toLon"] = $toLatLon["lon"];
        $element["strokeColor"] = $color;

        $this->elements[] = $element;
    }

    public function addCircle(array $latLon, string $color, float $radius)
    {
        $element = $this->createEmptyElement();

        $element["type"] = "circle";
        $element["lat"] = $latLon["lat"];
        $element["lon"] = $latLon["lon"];
        $element["strokeColor"] = $color;
        $element["radius"] = $radius;

        $this->elements[] = $element;
    }

    public function addText(array $latLon, string $color, string $text)
    {
        $element = $this->createEmptyElement();

        $element["type"] = "text";
        $element["lat"] = $latLon["lat"];
        $element["lon"] = $latLon["lon"];
        $element["fillColor"] = $color;
        $element["text"] = $text;

        $this->elements[] = $element;
    }

    public function toSvg(array $centerLatLon, float $radius)
    {
        $cx = $centerLatLon["lon"];
        // SVG Y-coordinates are reversed
        $cy = -1 * $centerLatLon["lat"];

        // Just approximate rx and ry
        $rx = $radius / (40075000 * cos(deg2rad($cy)) / 360);
        $ry = $radius / 111320;

        $minX = $cx - $rx;
        $minY = $cy - $ry;

        $w = ($cx + $rx) - $minX;
        $h = ($cy + $ry) - $minY;

        $imageW = 2000;
        $imageH = $imageW / ($w / $h);
        $pix = $w / $imageW;

        // Reasonable scaling factor to reduce rounding errors when svg is rendered
        $s = 1 / $pix;
        $s = ceil($s / 100) * 100;

        $cx = $s * $cx;
        $cy = $s * $cy;
        $rx = $s * $rx;
        $ry = $s * $ry;
        $minX = $s * $minX;
        $minY = $s * $minY;
        $w = $s * $w;
        $h = $s * $h;
        $pix = $s * $pix;

        $svg = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?> \n";
        $svg .= "<svg preserveAspectRatio = \"xMinYMin meet\" width=\"$imageW\" height=\"$imageH\" ";
        $svg .= "viewBox=\"$minX $minY $w $h\" ";
        $svg .= "xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">\n";
        $svg .= "<rect vector-effect=\"non-scaling-stroke\" stroke=\"black\" stroke-width=\"1px\" ";
        $svg .= "fill=\"#66f\" x=\"$minX\" y=\"$minY\" width=\"$w\" height=\"$h\"/>\n";

        foreach ($this->elements as $element) {
            $lon = $element["lon"];
            // SVG Y-coordinates are reversed
            $lat = -1 * $element["lat"];
            $toLon = $element["toLon"];
            // SVG Y-coordinates are reversed
            $toLat = -1 * $element["toLat"];
            $x = $lon * $s;
            $y = $lat * $s;
            $toX = $toLon * $s;
            $toY = $toLat * $s;
            $radius = $element["radius"] * $s;
            $strokeColor = $element["strokeColor"];
            $fillColor = $element["fillColor"];
            $text = $element["text"];

            if ($element["type"] === "point") {
                $r = 2 * $pix;
                $svg .= "<circle stroke-width=\"$pix\" stroke=\"$strokeColor\" fill=\"$fillColor\" ";
                $svg .= "cx=\"$x\" cy=\"$y\" r=\"$r\"/>\n";
            } elseif ($element["type"] === "line") {
                $svg .= "<line stroke-width=\"$pix\" x1=\"$x\" y1=\"$y\" x2=\"$toX\" y2=\"$toY\" ";
                $svg .= "stroke=\"$strokeColor\" /> \n";
            } elseif ($element["type"] === "circle") {
                // Just approximate rx and ry
                $rx = $radius / (40075000 * cos(deg2rad($lat)) / 360);
                $ry = $radius / 111320;
        
                $svg .= "<ellipse stroke-width=\"$pix\" stroke=\"$strokeColor\" fill=\"none\" ";
                $svg .= "cx=\"$x\" cy=\"$y\" rx=\"$rx\" ry=\"$ry\"/>\n";
            } elseif ($element["type"] === "text") {
                $size = 8 * $pix;
        
                $svg .= "<text x=\"$x\" y=\"$y\" font-family=\"sans-serif\" ";
                $svg .= "font-size=\"{$size}px\" fill=\"$fillColor\">$text</text>\n";
            }
        }

        $svg .= "</svg>";

        return $svg;
    }

    public function toKml()
    {
        $kml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $kml .= "<kml xmlns=\"http://www.opengis.net/kml/2.2\">\n";
        $kml .= "<Document>\n";
        $kml .= "<Placemark>\n";
        $kml .= "<LineString>\n";
        $kml .= "<coordinates>\n";

        foreach ($this->elements as $element) {
            $lat = $element["lat"];
            $lon = $element["lon"];

            $kml .= $lon . "," .$lat. "\n";
        }

        $kml .= "</coordinates>\n";
        $kml .= "</LineString>\n";
        $kml .= "</Placemark>\n";
        $kml .= "</Document>\n";
        $kml .= "</kml>\n";

        return $kml;
    }
}
