REM ffmpeg -y -i %1  -vcodec libx264 -crf 50 -f flv %1.flv
ffmpeg -y -i %1 -vcodec libx264 -pass 1 -vpre fastfirstpass -r 10 -b 579k -f flv %1.flv
ffmpeg -y -i %1 -vcodec libx264 -pass 2 -vpre hq -r 10 -b 579k -f flv %1.flv
