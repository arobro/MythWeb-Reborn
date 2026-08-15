#!/usr/bin/perl
#
# MythWeb Streaming/Download module
#
# @url       $URL$
# @date      $Date$
# @version   $Revision$
# @author    $Author$
#

    use POSIX qw(ceil floor);

# round to the nearest even integer
    sub round_even {
        my ($in) = @_;
        my $n = floor($in);
        return ($n % 2 == 0) ? $n : ceil($in);
    }

    our $ffmpeg_pid;
    our $ffmpeg_pgid;

# Shutdown cleanup, of various types
    $ffmpeg_pgid = setpgrp(0,0);
    $SIG{'TERM'} = \&shutdown_handler;
    $SIG{'PIPE'} = \&shutdown_handler;
    END {
        shutdown_handler();
    }
    sub shutdown_handler {
        kill(1, $ffmpeg_pid) if ($ffmpeg_pid);
        kill(-1, $ffmpeg_pgid) if ($ffmpeg_pgid);
    }

# Find ffmpeg
    $ffmpeg = '';
    foreach my $path (split(/:/, $ENV{'PATH'}.':/usr/local/bin:/usr/bin'), '.') {
        if (-e "$path/ffmpeg") {
            $ffmpeg = "$path/ffmpeg";
            last;
        }elsif (-e "$path/mythffmpeg") {
            $ffmpeg = "$path/mythffmpeg";
            last;
        }
        elsif ($^O eq 'darwin' && -e "$path/ffmpeg.app") {
            $ffmpeg = "$path/ffmpeg.app";
            last;
        }
    }

# Load some conversion settings from the database
    $sh = $dbh->prepare('SELECT data FROM settings WHERE value=? AND hostname IS NULL');
    $sh->execute('WebFLV_w');
    my ($width)    = $sh->fetchrow_array;
    $sh->execute('WebFLV_vb');
    my ($vbitrate) = $sh->fetchrow_array;
    $sh->execute('WebFLV_ab');
    my ($abitrate) = $sh->fetchrow_array;
    $sh->finish();

# auto-detect height based on aspect ratio
    $sh = $dbh->prepare('SELECT data FROM recordedmarkup WHERE chanid=? ' .
                        'AND starttime=FROM_UNIXTIME(?) AND type=30 ' .
                        'AND data IS NOT NULL ORDER BY mark LIMIT 1');
    $sh->execute($chanid,$starttime);
    $x = $sh->fetchrow_array;           # type = 30
    $sh->finish();

    $sh = $dbh->prepare('SELECT data FROM recordedmarkup WHERE chanid=? ' .
                        'AND starttime=FROM_UNIXTIME(?) AND type=31 ' .
                        'AND data IS NOT NULL ORDER BY mark LIMIT 1');
    $sh->execute($chanid,$starttime);
    $y = $sh->fetchrow_array if ($x);   # type = 31
    $sh->finish();

    if (!$x || !$y || $x <= 720) {      # <=720 means SD 
        $sh = $dbh->prepare('SELECT recordedmarkup.type, ' .
               'recordedmarkup.data ' .
               'FROM recordedmarkup ' .
               'WHERE recordedmarkup.chanid = ? ' .
               'AND recordedmarkup.starttime = FROM_UNIXTIME(?)  ' .
               'AND recordedmarkup.type IN (10, 11, 12, 13, 14) ' .
               'GROUP BY recordedmarkup.type  ' .
               'ORDER BY SUM((SELECT IFNULL(rm.mark, recordedmarkup.mark) ' .
               '   FROM recordedmarkup AS rm ' .
               '   WHERE rm.chanid = recordedmarkup.chanid ' .
               '   AND rm.starttime = recordedmarkup.starttime ' .
               '   AND rm.type IN (10, 11, 12, 13, 14)  ' .
               '   AND rm.mark > recordedmarkup.mark ' .
               '   ORDER BY rm.mark ASC LIMIT 1)- recordedmarkup.mark) DESC ' .
               'LIMIT 1'); 
        $sh->execute($chanid,$starttime); 
        $aspect = $sh->fetchrow_hashref; 
        $sh->finish(); 

        if( $aspect->{'type'} == 10 ) { 
            $x = $y = 1; 
        } elsif( $aspect->{'type'}== 11 ) { 
            $x = 4; $y = 3; 
        } elsif( $aspect->{'type'}== 12 ) { 
            $x = 16; $y = 9; 
        } elsif( $aspect->{'type'}== 13 ) { 
            $x = 2.21; $y = 1; 
        } elsif( $aspect->{'type'}== 14 ) { 
            $x = $aspect->{'data'}; $y = 10000; 
        } else { 
            $x = 4; $y = 3; 
        } 
    } 
    $height = round_even($width * ($y/$x)); 

    $width    = 320 unless ($width    && $width    > 1);
    $height   = 240 unless ($height   && $height   > 1);
    $vbitrate = 256 unless ($vbitrate && $vbitrate > 1);
    $abitrate = 64  unless ($abitrate && $abitrate > 1);

    my $ffmpeg_command = $ffmpeg
                        .' -y'
                        .' -i '.shell_escape($filename)
                        .' -s '.shell_escape("${width}x${height}")
                        .' -g 30'
                        .' -r 24'
                        .' -f webm'
                        .' -acodec libvorbis'
                        .' -vcodec libvpx'
                        .' -b:v '.shell_escape("${vbitrate}k")
                        .' -b:a '.shell_escape("${abitrate}k")
                        .' -';

# Print HTTP headers
    print "Content-Type: video/webm\r\n";
    print "Content-Disposition: inline; filename=\"".basename($filename).".webm\"\r\n";
    print "Cache-Control: no-cache\r\n\r\n";

# Open stream pipe from ffmpeg
    $ffmpeg_pid = open(my $pipe, "$ffmpeg_command 2>/dev/null |")
        or die "Cannot execute ffmpeg: $!";

    binmode($pipe);
    binmode(STDOUT);

    my $buffer;
    while (read($pipe, $buffer, 1024 * 64)) {
        print $buffer;
        STDOUT->flush();
    }

    close($pipe);
    1;