param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$OutputDirectory = "tmp\uth-old-logo-only-20261112"
)

Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = "Stop"

$root = [IO.Path]::GetFullPath($ProjectRoot)
$sourceDir = Join-Path $root "assets\presentation\x800-uth-theme"
$demoDir = Join-Path $root "tmp\rendered-slides-20261112"
$outDir = Join-Path $root $OutputDirectory
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

function New-Graphics([System.Drawing.Image]$image) {
    $g = [System.Drawing.Graphics]::FromImage($image)
    $g.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    return $g
}

for ($number = 1; $number -le 19; $number++) {
    if ($number -le 15) {
        $sourcePath = Join-Path $sourceDir ("x800-uth-slide-{0:00}.png" -f $number)
    }
    elseif ($number -le 18) {
        $sourcePath = Join-Path $demoDir ("slide-{0:00}.png" -f $number)
    }
    else {
        $sourcePath = Join-Path $sourceDir "x800-uth-slide-16.png"
    }

    if (-not (Test-Path $sourcePath)) { throw "Missing source: $sourcePath" }
    $outPath = Join-Path $outDir ("slide-{0:00}.png" -f $number)
    $tmpPath = "$outPath.tmp.png"
    $source = [System.Drawing.Bitmap]::new($sourcePath)
    $canvas = [System.Drawing.Bitmap]::new(1600, 900, [System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
    $g = New-Graphics $canvas
    try {
        $g.Clear([System.Drawing.Color]::White)
        $g.DrawImage($source, [System.Drawing.Rectangle]::new(0, 0, 1600, 900), [System.Drawing.Rectangle]::new(0, 0, $source.Width, $source.Height), [System.Drawing.GraphicsUnit]::Pixel)

        # Demo slides keep the old layout and UTH logo only; remove the extra school-name banner.
        if ($number -ge 16 -and $number -le 18) {
            $g.FillRectangle([System.Drawing.Brushes]::White, [System.Drawing.Rectangle]::new(1150, 0, 450, 95))
        }
        $canvas.Save($tmpPath, [System.Drawing.Imaging.ImageFormat]::Png)
    }
    finally {
        $g.Dispose()
        $canvas.Dispose()
        $source.Dispose()
    }
    Move-Item -LiteralPath $tmpPath -Destination $outPath -Force
}

Write-Output "Restored 19 old-layout logo-only slide images in $outDir"
