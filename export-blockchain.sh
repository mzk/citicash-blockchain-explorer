#!/usr/bin/env bash
~/mounted2/citicash-blockchain-export --data-dir ~/mounted2/.citicash --output-file ~/mounted2/blockchain.raw.tmp
md5sum ~/mounted2/blockchain.raw.tmp > ~/mounted2/blockchain.raw.md5sum.txt
cp ~/mounted2/blockchain.raw.tmp ~/mounted2/blockchain.raw
cp ~/mounted2/blockchain.raw.md5sum.txt /var/www/blockchain-explorer/www/blockchain.raw.md5sum.txt
#ln -s ~/mounted2/blockchain.raw /var/www/blockchain-explorer/www/blockchain.raw

scp /home/ubuntu/mounted2/blockchain.raw.md5sum.txt xx:/home/ubuntu/blockchain.raw.md5sum.txt
