#encoding=utf-8
#+-------------------------------------------------------+
# RailFence.py
# Copyright (c) 2015 OshynSong<dualyangsong@gmail.com>
#
# Encrypt/Decrypt the short text message by the rail fence
# cipher. Only surpport for ascii character.
#+-------------------------------------------------------+
'''
RailFence
This module contains the encrypt and decrypt for common
usage of rail fence cipher.
Usage:
>>> import RailFence
>>> obj = RailFence.RailFence([row=2[, mask=None]])
>>> obj.encrypt(src)
>>> obj.decrypt(dst)
or by directly usage
>>> RailFence.encrypt(src, [row=2[, mask=None]])
>>> RailFence.decrypt(dst, [row=2[, mask=None]])

'''
from __future__ import division
import string
import types
import math

CHARS = string.ascii_uppercase + string.ascii_lowercase

class RailFence:
    '''The rail fence cipher class definition'''
    def __init__(self, row = 2, mask = None):
        if row < 2:
            raise ValueError(u'Not acceptable row number or mask value')
        self.Row    = row
        if mask != None and not isinstance(mask, (types.StringType, types.UnicodeType)):
            raise ValueError(u'Not acceptable mask value')
        self.Mask   = mask
        self.Length = 0
        self.Column = 0
    
    def encrypt(self, src, nowhitespace = False):
        if not isinstance(src, (types.StringType, types.UnicodeType)):
            raise TypeError(u'Encryption src text is not string')
        if nowhitespace:
            self.NoWhiteSpace = ''
            for i in src:
                if i in string.whitespace: continue
                self.NoWhiteSpace += i
        else:
            self.NoWhiteSpace = src
        
        self.Length = len(self.NoWhiteSpace)
        self.Column = int(math.ceil(self.Length / self.Row))
        try:
            self.__check()
        except Exception, msg:
            print msg
        #get mask order
        self.__getOrder()
        
        grid = [[] for i in range(self.Row)]
        for c in range(self.Column):
            endIndex = (c + 1) * self.Row
            if endIndex > self.Length:
                endIndex = self.Length
            r = self.NoWhiteSpace[c * self.Row : endIndex]
            for i,j in enumerate(r):
                if self.Mask != None and len(self.Order) > 0:
                    grid[self.Order[i]].append(j)
                else:
                    grid[i].append(j)
        return ''.join([''.join(l) for l in grid])
    
    def decrypt(self, dst):
        if not isinstance(dst, (types.StringType, types.UnicodeType)):
            raise TypeError(u'Decryption dst text is not string')
        self.Length = len(dst)
        self.Column = int(math.ceil(self.Length / self.Row))
        try:
            self.__check()
        except Exception, msg:
            print msg
        #get mask order
        self.__getOrder()
        
        grid  = [[] for i in range(self.Row)]
        space = self.Row * self.Column - self.Length
        ns    = self.Row - space
        prevE = 0
        for i in range(self.Row):
            if self.Mask != None:
                s = prevE
                O = 0
                for x,y in enumerate(self.Order):
                    if i == y:
                        O = x
                        break
                if O < ns: e = s + self.Column
                else: e = s + (self.Column - 1)
                r = dst[s : e]
                prevE = e
                grid[O] = list(r)
            else:
                startIndex = 0
                endIndex   = 0
                if i < self.Row - space:
                    startIndex = i * self.Column
                    endIndex   = startIndex + self.Column
                else:                
                    startIndex = ns * self.Column + (i - ns) * (self.Column - 1)
                    endIndex   = startIndex + (self.Column - 1)
                r = dst[startIndex:endIndex]
                grid[i] = list(r)
        res = ''
        for c in range(self.Column):
            for i in range(self.Row):
                line = grid[i]
                if len(line) == c:
                    res += ' '
                else:
                    res += line[c]
        return res
    
    def __check(self):
        #The length of column must be equal or bigger than 2
        if self.Column < 2:
            raise Error(u'Unexpected column number')
        
        #The length of the mask must be equal to row
        if self.Mask != None and len(self.Mask) != self.Row:
            raise ValueError(u'Mask length not match, must be equal to row')
    
    def __getOrder(self):
        self.Order = []
        if self.Mask != None:
            maskOrder = []
            for i in self.Mask:
                maskOrder.append(CHARS.index(i))            
            ordered = sorted(maskOrder, reverse=False)
            for i in range(self.Row):
                now = maskOrder[i]
                for j,k in enumerate(ordered):
                    if k == now:
                        self.Order.append(j)
                        break

def encrypt(src, row = 2, mask = None, nowhitespace = False):
    rf = RailFence(row, mask)
    return rf.encrypt(src, nowhitespace)

def decrypt(dst, row = 2, mask = None):
    rf = RailFence(row, mask)
    return rf.decrypt(dst)

def test():
    print 'By directly method:'
    e = encrypt('the anwser is wctf{C01umnar},if u is a big new,u can help us think more question,tks.', 4, 'bcaf', True)
    print e
    print decrypt(e, 4, 'bcaf')
    
    print 'By class object:'
    rf = RailFence(4, 'bcaf')
    e = rf.encrypt('the anwser is wctf{C01umnar},if u is a big new,u can help us think more question,tks.')
    print "Encrypt: ",e
    print "Decrypt: ", rf.decrypt(e)

if __name__ == '__main__':
    test()
