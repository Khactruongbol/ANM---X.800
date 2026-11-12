param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$OutputDirectory = "tmp\uth-theme-regenerated-20261112"
)

Add-Type -AssemblyName System.Drawing

$ErrorActionPreference = "Stop"
$root = [IO.Path]::GetFullPath($ProjectRoot)
$outDir = Join-Path $root $OutputDirectory
$sourceDir = Join-Path $root "assets\presentation\x800-uth-theme"
$themePath = "C:\Temp\uth-thmx-extracted-20261112\theme\media\image1.jpg"

function New-Graphics([System.Drawing.Image]$image) {
    $g = [System.Drawing.Graphics]::FromImage($image)
    $g.CompositingMode = [System.Drawing.Drawing2D.CompositingMode]::SourceOver
    $g.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    return $g
}

function Draw-CroppedSource(
    [System.Drawing.Graphics]$g,
    [string]$sourcePath,
    [int]$sourceY,
    [int]$sourceHeight,
    [int]$destY,
    [int]$destHeight
) {
    $source = [System.Drawing.Bitmap]::new($sourcePath)
    try {
        $srcRect = [System.Drawing.Rectangle]::new(0, $sourceY, $source.Width, $sourceHeight)
        $dstRect = [System.Drawing.Rectangle]::new(0, $destY, 1600, $destHeight)
        $g.DrawImage($source, $dstRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)
    }
    finally {
        $source.Dispose()
    }
}

function Draw-CenteredText(
    [System.Drawing.Graphics]$g,
    [string]$text,
    [System.Drawing.Font]$font,
    [System.Drawing.Brush]$brush,
    [System.Drawing.RectangleF]$rect
) {
    $fmt = [System.Drawing.StringFormat]::new()
    $fmt.Alignment = [System.Drawing.StringAlignment]::Center
    $fmt.LineAlignment = [System.Drawing.StringAlignment]::Center
    $fmt.Trimming = [System.Drawing.StringTrimming]::None
    try {
        $g.DrawString($text, $font, $brush, $rect, $fmt)
    }
    finally {
        $fmt.Dispose()
    }
}

function Draw-ThemeCurve([System.Drawing.Graphics]$g, [string]$themePath) {
    $theme = [System.Drawing.Bitmap]::new($themePath)
    try {
        # Use only the lower curve strip from the UTH master so each slide has one frame.
        $srcRect = [System.Drawing.Rectangle]::new(0, 630, $theme.Width, 138)
        $dstRect = [System.Drawing.Rectangle]::new(0, 780, 1600, 120)
        $g.DrawImage($theme, $dstRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)
    }
    finally {
        $theme.Dispose()
    }
}

$titles = @(
    "GIỚI THIỆU ĐỀ TÀI",
    "MỤC TIÊU VÀ PHẠM VI",
    "MÔ HÌNH ÁNH XẠ X.800",
    "TẤN CÔNG THỤ ĐỘNG",
    "TẤN CÔNG CHỦ ĐỘNG",
    "NĂM DỊCH VỤ BẢO MẬT X.800",
    "XÁC THỰC",
    "KIỂM SOÁT TRUY CẬP VÀ RBAC",
    "BÍ MẬT VÀ TLS",
    "TOÀN VẸN VÀ CSRF",
    "KHÔNG THỂ CHỐI BỎ",
    "CƠ CHẾ CHUYÊN BIỆT",
    "CƠ CHẾ PHỔ QUÁT",
    "ÁNH XẠ VÀO CÁC LỚP HỆ THỐNG",
    "WORKFLOW REQUEST BẢO MẬT",
    "DEMO X.800 — TLS/HTTPS VÀ XÁC THỰC",
    "DEMO X.800 — KIỂM SOÁT TRUY CẬP",
    "DEMO X.800 — AUDIT VÀ TOÀN VẸN",
    "KẾT LUẬN"
)

$logoPath = Join-Path $sourceDir "uth-logo.png"
$arialBoldPath = Join-Path $env:WINDIR "Fonts\arialbd.ttf"
$timesBoldPath = Join-Path $env:WINDIR "Fonts\timesbd.ttf"
$logo = [System.Drawing.Bitmap]::new($logoPath)
$headerBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(0, 128, 128))
$titleBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(18, 88, 158))
$whiteBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::White)
$headerFont = [System.Drawing.Font]::new($timesBoldPath, 30, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)

try {
    for ($i = 0; $i -lt $titles.Count; $i++) {
        $number = $i + 1
        $name = "slide-{0:00}.png" -f $number
        $outPath = Join-Path $outDir $name
        if (-not (Test-Path $outPath)) { throw "Missing current slide image: $outPath" }

        $tmp = "$outPath.tmp.png"
        $canvas = [System.Drawing.Bitmap]::new(1600, 900, [System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
        $g = New-Graphics $canvas
        try {
            $g.Clear([System.Drawing.Color]::White)

            # Rebuild the body from uncropped source artwork and leave the bottom strip
            # exclusively to the UTH master curve. This prevents the previous double-curve.
            $sourceY = 150
            $sourceHeight = 680
            $bodySource = $null
            if ($number -le 15) {
                $bodySource = Join-Path $sourceDir ("x800-uth-slide-{0:00}.png" -f $number)
            }
            elseif ($number -le 18) {
                $bodySource = Join-Path $root ("tmp\rendered-slides-20261112\slide-{0:00}.png" -f $number)
                $sourceY = 100
                $sourceHeight = 735
            }
            else {
                $bodySource = Join-Path $sourceDir "x800-uth-slide-16.png"
            }

            $bodyDestY = 175
            $bodyHeight = 605
            $cleanupSourceCurve = $false
            if ($number -eq 3) { $sourceY = 210; $sourceHeight = 620 }
            elseif ($number -eq 4) {
                $sourceY = 95
                $sourceHeight = 790
                $bodyDestY = 180
                $bodyHeight = 600
                $cleanupSourceCurve = $true
            }
            elseif ($number -eq 8) { $sourceY = 92; $sourceHeight = 748 }
            elseif ($number -eq 13) { $sourceY = 180; $sourceHeight = 650 }
            elseif ($number -eq 19) { $sourceY = 200; $sourceHeight = 610 }

            if ($number -eq 19) { $bodyHeight = 580 }
            Draw-CroppedSource $g $bodySource $sourceY $sourceHeight $bodyDestY $bodyHeight
            if ($cleanupSourceCurve) {
                $g.FillRectangle($whiteBrush, [System.Drawing.Rectangle]::new(0, 760, 1600, 20))
            }
            Draw-ThemeCurve $g $themePath

            # Clear the entire branding/title band so the source artwork can never overlap it.
            $g.FillRectangle($whiteBrush, [System.Drawing.Rectangle]::new(0, 0, 1600, 175))
            $g.DrawImage($logo, [System.Drawing.Rectangle]::new(40, 25, 500, 145))

            $headerRect = [System.Drawing.RectangleF]::new(610, 4, 950, 102)
            Draw-CenteredText $g "TRƯỜNG ĐẠI HỌC GIAO THÔNG VẬN TẢI`nTHÀNH PHỐ HỒ CHÍ MINH" $headerFont $headerBrush $headerRect

            $title = $titles[$i]
            $fontSize = 25
            if ($title.Length -gt 32) { $fontSize = 20 }
            elseif ($title.Length -gt 25) { $fontSize = 22 }
            $titleFont = [System.Drawing.Font]::new($arialBoldPath, $fontSize, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
            try {
                $titleRect = [System.Drawing.RectangleF]::new(570, 108, 990, 48)
                Draw-CenteredText $g $title $titleFont $titleBrush $titleRect
            }
            finally {
                $titleFont.Dispose()
            }
        }
        finally {
            $canvas.Save($tmp, [System.Drawing.Imaging.ImageFormat]::Png)
            $g.Dispose()
            $canvas.Dispose()
        }

        # Save through a temporary file so the source PNG is not locked while being replaced.
        Move-Item -LiteralPath $tmp -Destination $outPath -Force
    }
}
finally {
    $logo.Dispose()
    $headerBrush.Dispose()
    $titleBrush.Dispose()
    $whiteBrush.Dispose()
    $headerFont.Dispose()
}

Write-Output "Regenerated $($titles.Count) UTH-branded slide images in $outDir"
